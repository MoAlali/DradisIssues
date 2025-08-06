<?php


$apiKey = '{dradispro api key it in your profile}';
$baseUrl = 'https://{dradispro url}/pro/api';

function callApi($endpoint, $params = [], $projectIdHeader = null) {
 global $apiKey, $baseUrl;

    $url = $baseUrl . $endpoint;
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $headers = [
        "Authorization: Token token=$apiKey",
    ];

    if ($projectIdHeader) {
        $headers[] = "Dradis-Project-Id: $projectIdHeader";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $resp = curl_exec($ch);
    
    if ($resp === false) {
        echo "<div class='alert alert-danger'>cURL Error: " . curl_error($ch) . "</div>";
        return [];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode !== 200) {
        echo "<div class='alert alert-danger'><strong>API Error (HTTP $httpCode):</strong> " . htmlspecialchars($resp) . "</div>";
    }
    
    curl_close($ch);
    return json_decode($resp, true);
}

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-2 years'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$projects = callApi('/projects');

$allIssues = [];
$projectsWithIssues = 0;
$projectMapping = [];

if (!empty($projects)) {
    foreach ($projects as $project) {
        $projectId = $project['id'];
        $projectName = $project['name'] ?? 'Unknown Project';
        $projectMapping[$projectId] = $projectName;
        
        $projectIssues = callApi("/projects/$projectId/issues");
        
        if (empty($projectIssues)) {
            $projectIssues = callApi("/issues", [], $projectId);
        }
        
        if (!empty($projectIssues)) {
            $projectsWithIssues++;
            foreach ($projectIssues as &$issue) {
                $issue['source_project_id'] = $projectId;
                $issue['source_project_name'] = $projectName;
            }
            $allIssues = array_merge($allIssues, $projectIssues);
        }
    }
}

$userStats = [];
$processedIssues = 0;
$filteredByDate = 0;

foreach ($allIssues as $issue) {
    $issueDate = $issue['created_at'] ?? $issue['created'] ?? $issue['date'] ?? $issue['updated_at'] ?? null;
    $includeIssue = true;
    
    if ($issueDate) {
        $issueTimestamp = strtotime($issueDate);
        if ($issueTimestamp === false) {
            $issueTimestamp = strtotime(str_replace('T', ' ', str_replace('Z', '', $issueDate)));
        }
        
        if ($issueTimestamp !== false) {
            $startTimestamp = strtotime($startDate);
            $endTimestamp = strtotime($endDate . ' 23:59:59');
            
            if ($issueTimestamp < $startTimestamp || $issueTimestamp > $endTimestamp) {
                $filteredByDate++;
                $includeIssue = false;
            }
        }
    }
    
    if (!$includeIssue) {
        continue;
    }
    
    $processedIssues++;
    
    $issueAuthor = $issue['author'] ?? null;
    
    if (!$issueAuthor) {
        continue;
    }
    
    $authorEmail = is_array($issueAuthor) ? 
        ($issueAuthor['email'] ?? $issueAuthor['name'] ?? $issueAuthor['login'] ?? 'Unknown') : 
        $issueAuthor;
    
    $systemUsers = [
        'Nexpose upload plugin',
        'Acunetix upload plugin', 
        'Qualys upload plugin',
        'Nessus upload plugin',
        'Burp upload plugin',
        'OpenVAS upload plugin',
        'Nmap upload plugin',
        'system',
        'admin',
        'dradis',
        'plugin'
    ];
    
    $isSystemUser = false;
    foreach ($systemUsers as $systemUser) {
        if (stripos($authorEmail, $systemUser) !== false) {
            $isSystemUser = true;
            break;
        }
    }
    
    if (!$isSystemUser && !strpos($authorEmail, '@') && !preg_match('/\.(com|org|net|edu|gov)/', $authorEmail)) {
        $isSystemUser = true;
    }
    
    if ($isSystemUser) {
        continue;
    }
    
    if (!isset($userStats[$authorEmail])) {
        $userStats[$authorEmail] = [
            'user' => $authorEmail,
            'total' => 0,
            'high' => 0,
            'critical' => 0,
            'projects' => [],
        ];
    }
    
    $userStats[$authorEmail]['total']++;
    
    $projectId = $issue['source_project_id'] ?? $issue['project_id'] ?? null;
    
    if (!$projectId) {
        $projectId = $issue['source_project_name'] ?? 'project_' . ($issue['id'] ?? uniqid());
    }
    
    if ($projectId && !in_array($projectId, $userStats[$authorEmail]['projects'])) {
        $userStats[$authorEmail]['projects'][] = $projectId;
    }
    
    $tags = $issue['tags'] ?? [];
    $severity = '';
    
    if (is_array($tags) && !empty($tags)) {
        foreach ($tags as $tag) {
            if (is_array($tag) && isset($tag['display_name'])) {
                $severity .= $tag['display_name'] . ',';
            } elseif (is_string($tag)) {
                $severity .= $tag . ',';
            }
        }
    }
    
    if (empty($severity) && isset($issue['fields']['Rating'])) {
        $severity = $issue['fields']['Rating'];
    }
    
    if (empty($severity)) {
        $severity = $issue['severity'] ?? $issue['priority'] ?? $issue['risk_level'] ?? '';
    }
    
    if (stripos($severity, 'High') !== false) {
        $userStats[$authorEmail]['high']++;
    }
    if (stripos($severity, 'Critical') !== false) {
        $userStats[$authorEmail]['critical']++;
    }
}

$results = [];
foreach ($userStats as $userStat) {
    $results[] = [
        'user' => $userStat['user'],
        'reports' => count($userStat['projects']),
        'total' => $userStat['total'],
        'high' => $userStat['high'],
        'critical' => $userStat['critical'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Dradis Issues Discovered by Users & Date</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container my-5">
    <h1 class="mb-4">Issues Discovered by Users</h1>

    <form method="GET" class="row g-3 mb-4 align-items-center">
        <div class="col-auto">
            <label for="start_date" class="col-form-label">Start Date</label>
        </div>
        <div class="col-auto">
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" />
        </div>
        <div class="col-auto">
            <label for="end_date" class="col-form-label">End Date</label>
        </div>
        <div class="col-auto">
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" />
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <?php if (empty($results)): ?>
        <div class="alert alert-warning">No data found for the selected date range.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>User</th>
                    <th>Reports</th>
                    <th>Total Issues Discovered</th>
                    <th>High Issues Discovered</th>
                    <th>Critical Issues Discovered</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['user']) ?></td>
                    <td><?= $row['reports'] ?></td>
                    <td><?= $row['total'] ?></td>
                    <td><?= $row['high'] ?></td>
                    <td><?= $row['critical'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
