param(
    [string]$XamppPath = "D:\xampp"
)

$ErrorActionPreference = "Stop"

$paths = @(
    (Join-Path $XamppPath "tmp"),
    (Join-Path $XamppPath "mysql\data"),
    (Join-Path $XamppPath "apache\logs")
)

Get-Process | Where-Object { $_.ProcessName -in @("httpd", "mysql", "mysqld", "mariadbd") } | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

foreach ($path in $paths) {
    if (-not (Test-Path -LiteralPath $path)) {
        New-Item -ItemType Directory -Path $path | Out-Null
    }

    Write-Host "Fixing permissions for $path"
    & takeown /F $path /R /D Y | Out-Null
    & icacls $path /inheritance:e | Out-Null
    & icacls $path /grant "Everyone:(OI)(CI)F" /T | Out-Null
    & attrib -R "$path\*" /S /D
}

$tmpPath = Join-Path $XamppPath "tmp"
Write-Host "Removing old temporary session files..."
Get-ChildItem -LiteralPath $tmpPath -Force -ErrorAction SilentlyContinue |
    Where-Object { -not $_.PSIsContainer } |
    Remove-Item -Force -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Done. Start XAMPP as Administrator, then start Apache and MySQL."
