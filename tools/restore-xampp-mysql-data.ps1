param(
    [string]$XamppMysqlPath = "D:\xampp\mysql"
)

$ErrorActionPreference = "Stop"

$dataPath = Join-Path $XamppMysqlPath "data"
$backupSourcePath = Join-Path $XamppMysqlPath "data_old"

if (-not (Test-Path -LiteralPath $dataPath)) {
    throw "Current MySQL data folder was not found: $dataPath"
}

if (-not (Test-Path -LiteralPath $backupSourcePath)) {
    throw "Backup MySQL data folder was not found: $backupSourcePath"
}

$mysqlProcesses = Get-Process | Where-Object { $_.ProcessName -in @("mysql", "mysqld", "mariadbd") }
if ($mysqlProcesses) {
    Write-Host "Stopping running MySQL process(es)..."
    $mysqlProcesses | Stop-Process -Force
    Start-Sleep -Seconds 3
}

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$brokenBackupPath = Join-Path $XamppMysqlPath "data_broken_$stamp"

Write-Host "Renaming current broken data folder to:"
Write-Host "  $brokenBackupPath"
Rename-Item -LiteralPath $dataPath -NewName (Split-Path $brokenBackupPath -Leaf)

Write-Host "Restoring healthy backup folder:"
Write-Host "  $backupSourcePath"
Write-Host "to:"
Write-Host "  $dataPath"
Copy-Item -LiteralPath $backupSourcePath -Destination $dataPath -Recurse

Write-Host "Resetting restored folder permissions..."
& icacls $dataPath /grant "Everyone:(OI)(CI)F" /T | Out-Null
& attrib -R "$dataPath\*" /S /D

Write-Host ""
Write-Host "Done. Open XAMPP Control Panel and start MySQL."
Write-Host "If MySQL starts, run this inside the CRM project:"
Write-Host "  php artisan migrate"
