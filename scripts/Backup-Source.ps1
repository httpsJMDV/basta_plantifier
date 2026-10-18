param([string]$Destination)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Split-Path -Parent $PSScriptRoot)).Path
if (!$Destination) { $Destination = Join-Path $projectRoot 'backups' }
$null = New-Item -ItemType Directory -Path $Destination -Force
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$archivePath = Join-Path $Destination ("plantifier-source-" + $stamp + ".zip")
# Explicit allowlist: no .env, private media, database dumps, credentials, dependencies, caches or Git metadata.
$allowed = @('backend/app','backend/bootstrap/app.php','backend/bootstrap/providers.php','backend/config','backend/database/setup-mysql.sql','backend/database/migrations','backend/database/factories','backend/database/seeders','backend/resources/views','backend/routes','backend/tests','backend/public/index.php','backend/public/.htaccess','backend/public/favicon.svg','backend/artisan','backend/composer.json','backend/composer.lock','backend/phpunit.xml','backend/.env.example','frontend/src','frontend/tests','frontend/package.json','frontend/package-lock.json','frontend/tsconfig.json','frontend/vite.config.js','frontend/playwright.config.ts','scripts','.gitignore','README.md','IMPLEMENTATION.md','PlantifierAI_Project_Blueprint.md')
$allowed += @('backend/bootstrap/cache/.gitignore','backend/storage/app/.gitignore','backend/storage/app/private/.gitignore','backend/storage/app/public/.gitignore','backend/storage/framework/.gitignore','backend/storage/framework/cache/.gitignore','backend/storage/framework/cache/data/.gitignore','backend/storage/framework/cache/locks/.gitignore','backend/storage/framework/sessions/.gitignore','backend/storage/framework/views/.gitignore','backend/storage/framework/testing/.gitignore','backend/storage/logs/.gitignore')
Push-Location -LiteralPath $projectRoot
try {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($item in $allowed) {
            $target = Join-Path $projectRoot $item
            if (!(Test-Path -LiteralPath $target)) { continue }
            $entry = Get-Item -LiteralPath $target
            if ($entry.PSIsContainer) { $sourceFiles = Get-ChildItem -LiteralPath $target -File -Recurse }
            else { $sourceFiles = @($entry) }
            foreach ($file in $sourceFiles) {
                $relative = $file.FullName.Substring($projectRoot.Length + 1).Replace([IO.Path]::DirectorySeparatorChar, [char]'/')
                if ($relative -match '(^|/)(\.env|auth\.json)(/|$)' -or $relative -match '\.sqlite') { throw 'Unexpected private file in source allowlist.' }
                $null = [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$file.FullName,$relative)
            }
        }
    } finally { $zip.Dispose() }
} finally { Pop-Location }
Write-Output ("Source-only snapshot: " + $archivePath)
