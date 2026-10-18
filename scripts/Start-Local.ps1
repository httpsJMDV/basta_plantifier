param([int]$Port = 8024, [switch]$Reverb)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $projectRoot 'backend'
$env:PHP_INI_SCAN_DIR = Join-Path $PSScriptRoot 'php'
Set-Location -LiteralPath $backendRoot
if (!(Test-Path -LiteralPath '.env')) { throw 'Configure backend/.env first. See README.md.' }
if (!(Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Run composer install from backend/ first.' }
if ($Reverb) { & php artisan reverb:start --host=127.0.0.1 --port=8084 }
else {
    if (!(Test-Path -LiteralPath 'public/build/manifest.json')) { throw 'Run npm.cmd install and npm.cmd run build from frontend/ first.' }
    & php -d upload_max_filesize=5M -d post_max_size=16M artisan serve --host=127.0.0.1 --port=$Port
}
