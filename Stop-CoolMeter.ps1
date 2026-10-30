$pidFile = Join-Path $PSScriptRoot 'storage/server.pid'
if (Test-Path -LiteralPath $pidFile) {
    $serverPid = [int](Get-Content -LiteralPath $pidFile)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $serverPid" -ErrorAction SilentlyContinue
    if ($process -and $process.CommandLine -like '*artisan*serve*--port=8000*') {
        Get-CimInstance Win32_Process -Filter "ParentProcessId = $serverPid" | ForEach-Object { Stop-Process -Id $_.ProcessId -ErrorAction SilentlyContinue }
        Stop-Process -Id $serverPid -ErrorAction SilentlyContinue
        Write-Host 'CoolMeter stopped.'
    }
    Remove-Item -LiteralPath $pidFile
}
