param(
    [string]$MysqlDataPath = "D:\xampp\mysql\data"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $MysqlDataPath)) {
    throw "MySQL data folder was not found: $MysqlDataPath"
}

$mysqlProcesses = Get-Process | Where-Object { $_.ProcessName -in @("mysql", "mysqld", "mariadbd") }
if ($mysqlProcesses) {
    Write-Host "Stopping running MySQL process(es)..."
    $mysqlProcesses | Stop-Process -Force
    Start-Sleep -Seconds 3
}

Write-Host "Taking ownership of the MySQL data folder..."
& takeown /F $MysqlDataPath /R /D Y | Out-Null

Write-Host "Granting write permissions to the MySQL data folder..."
& icacls $MysqlDataPath /grant "Everyone:(OI)(CI)F" /T | Out-Null

Write-Host "Removing read-only flags..."
& attrib -R "$MysqlDataPath\*" /S /D

Write-Host ""
Write-Host "Done. Open XAMPP Control Panel and start MySQL."
