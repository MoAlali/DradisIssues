# Dradis Issues Dashboard

A PHP dashboard to analyze issues discovered by users in Dradis projects, with date filtering and statistics.

## Features
- View issues discovered by users across all Dradis projects
- Filter by date range
- Exclude system/automated users
- Statistics for High and Critical issues
- Bootstrap-based responsive UI

## Requirements

- PHP 7.0 or higher
- PHP cURL extension enabled

## Setup

1. Clone this repository
2. Ensure PHP cURL extension is installed and enabled:
   - On Ubuntu/Debian: `sudo apt-get install php-curl`
   - On CentOS/RHEL: `sudo yum install php-curl`
   - On Windows: Uncomment `extension=curl` in your `php.ini` file
3. Edit `dashboard.php` and replace the placeholders:
   - Replace `{dradispro api key it in your profile}` with your actual Dradis API key
   - Replace `{dradispro url}` with your actual Dradis Pro URL

## Configuration

### API Key
Get your API key from your Dradis Pro profile settings.

### Base URL
Use your Dradis Pro instance URL (e.g., `https://yourcompany.dradispro.com`)

## Usage

1. Access the dashboard via web browser
2. Use the date filters to specify the range of issues to analyze
3. View the statistics table showing user contributions

## Security Notes
- Never commit actual API keys or URLs to version control
- Keep your configuration files secure
- Use environment variables or separate config files for production
