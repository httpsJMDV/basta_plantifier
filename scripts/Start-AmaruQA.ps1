param([switch]$Reverb)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $projectRoot 'backend'
Set-Location -LiteralPath $backendRoot
if (!(Test-Path -LiteralPath '.env.amaru-qa')) { throw 'Prepare the dedicated .env.amaru-qa and database/amaru-qa.sqlite first. See AMARU_PROGRESS.md.' }
$qaConfig = Get-Content -LiteralPath '.env.amaru-qa'
if (!($qaConfig -contains 'DB_CONNECTION=sqlite') -or !($qaConfig | Where-Object { $_ -match '^DB_DATABASE=.*amaru-qa\.sqlite$' })) { throw 'QA must use its dedicated SQLite database.' }
$listenPort = if ($Reverb) { 8085 } else { 8025 }
if ([System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners() | Where-Object { $_.Port -eq $listenPort }) { throw "QA port $listenPort is already in use." }
$env:PHP_INI_SCAN_DIR = Join-Path $PSScriptRoot 'php'
if ($Reverb) { & php artisan reverb:start --host=127.0.0.1 --port=8085 --env=amaru-qa }
else { & php artisan serve --host=127.0.0.1 --port=8025 --tries=0 --env=amaru-qa --no-reload }
