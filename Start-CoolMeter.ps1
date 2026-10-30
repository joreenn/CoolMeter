$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else { 'C:\xampp\php\php.exe' }
if (!(Test-Path -LiteralPath $phpPath)) { throw 'PHP 8.2 or newer is required.' }
if (!(Test-Path -LiteralPath (Join-Path $PSScriptRoot 'vendor/autoload.php'))) { throw 'Run composer install first. See README.md.' }
if (!(Test-Path -LiteralPath (Join-Path $PSScriptRoot '.env'))) {
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot '.env.example') -Destination (Join-Path $PSScriptRoot '.env')
    & $phpPath artisan key:generate
    if ($LASTEXITCODE -ne 0) { throw 'Could not generate the application key.' }
}
if (!(Test-Path -LiteralPath (Join-Path $PSScriptRoot 'database/database.sqlite'))) {
    New-Item -ItemType File -Path (Join-Path $PSScriptRoot 'database/database.sqlite') | Out-Null
}
& $phpPath artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Could not prepare the database.' }
Write-Host 'CoolMeter: http://localhost:8000'
Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -ne '127.0.0.1' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -eq 'Dhcp' } | ForEach-Object { Write-Host ('Android on home Wi-Fi: http://' + $_.IPAddress + ':8000') }
$existing = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($existing) { Write-Host 'Port 8000 is already in use. Open the link if CoolMeter is already running.'; exit }
$process = Start-Process -FilePath $phpPath -ArgumentList @('artisan','serve','--host=0.0.0.0','--port=8000','--no-reload') -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $PSScriptRoot 'storage/logs/server.log') -RedirectStandardError (Join-Path $PSScriptRoot 'storage/logs/server-error.log')
$process.Id | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'storage/server.pid')
Write-Host 'Started in the background. Use Stop-CoolMeter.ps1 to stop it.'
