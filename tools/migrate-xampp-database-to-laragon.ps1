param(
    [string]$Database = "crm_3in1"
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$xamppMysql = "D:\xampp\mysql\bin"
$xamppData = "D:\xampp\mysql\data"
$laragonRoot = "D:\laragon"
$laragonMysql = Get-ChildItem "$laragonRoot\bin\mysql" -Directory |
    Sort-Object Name -Descending |
    Select-Object -First 1

if (-not $laragonMysql) {
    throw "Laragon MySQL was not found in $laragonRoot\bin\mysql."
}

$oldServer = Join-Path $xamppMysql "mysqld.exe"
$oldDump = Join-Path $xamppMysql "mysqldump.exe"
$newClient = Join-Path $laragonMysql.FullName "bin\mysql.exe"

foreach ($path in @($oldServer, $oldDump, $newClient, "$xamppData\ibdata1")) {
    if (-not (Test-Path $path)) {
        throw "Required file was not found: $path"
    }
}

$backupDirectory = Join-Path $projectRoot "storage\backups"
$recoveryTmp = Join-Path $laragonRoot "tmp\mariadb-recovery"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$dumpFile = Join-Path $backupDirectory "$Database-$timestamp.sql"
$serverOutput = Join-Path $backupDirectory "mariadb-recovery-$timestamp.out.log"
$serverError = Join-Path $backupDirectory "mariadb-recovery-$timestamp.error.log"
$dumpError = Join-Path $backupDirectory "mariadb-dump-$timestamp.error.log"
$importOutput = Join-Path $backupDirectory "mysql-import-$timestamp.out.log"
$importError = Join-Path $backupDirectory "mysql-import-$timestamp.error.log"

New-Item -ItemType Directory -Force -Path $backupDirectory, $recoveryTmp | Out-Null

$portInUse = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if ($portInUse) {
    throw "Port 3307 is already in use. Close the other database process and run this helper again."
}

Write-Host "Starting the restored MariaDB database on temporary port 3307..."
$serverArguments = @(
    "--defaults-file=D:\xampp\mysql\bin\my.ini",
    "--datadir=$xamppData",
    "--tmpdir=$recoveryTmp",
    "--port=3307",
    "--bind-address=127.0.0.1",
    "--skip-grant-tables",
    "--console"
)

$recoveryServer = Start-Process -FilePath $oldServer `
    -ArgumentList $serverArguments `
    -WindowStyle Hidden `
    -RedirectStandardOutput $serverOutput `
    -RedirectStandardError $serverError `
    -PassThru

try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        Start-Sleep -Seconds 1
        if ($recoveryServer.HasExited) {
            $details = Get-Content $serverError -Raw -ErrorAction SilentlyContinue
            throw "The restored MariaDB database could not start.`n$details"
        }

        $client = [System.Net.Sockets.TcpClient]::new()
        try {
            $client.Connect("127.0.0.1", 3307)
            $ready = $true
            break
        } catch {
            # Keep waiting while MariaDB completes crash recovery.
        } finally {
            $client.Dispose()
        }
    }

    if (-not $ready) {
        throw "MariaDB did not become ready on port 3307 within 20 seconds."
    }

    Start-Sleep -Seconds 1
    if ($recoveryServer.HasExited) {
        $details = Get-Content $serverError -Raw -ErrorAction SilentlyContinue
        throw "The restored MariaDB database stopped before export.`n$details"
    }

    Write-Host "Exporting $Database to $dumpFile..."
    $dumpArguments = @(
        "--host=127.0.0.1",
        "--port=3307",
        "--user=root",
        "--single-transaction",
        "--routines",
        "--triggers",
        "--events",
        "--default-character-set=utf8mb4",
        "--result-file=$dumpFile",
        $Database
    )
    $dumpProcess = Start-Process -FilePath $oldDump `
        -ArgumentList $dumpArguments `
        -RedirectStandardError $dumpError `
        -Wait `
        -PassThru `
        -NoNewWindow
    if ($dumpProcess.ExitCode -ne 0 -or -not (Test-Path $dumpFile) -or (Get-Item $dumpFile).Length -eq 0) {
        $details = Get-Content $dumpError -Raw -ErrorAction SilentlyContinue
        throw "The SQL export failed. The Laragon database was not changed.`n$details"
    }

    Write-Host "Creating a clean Laragon database..."
    & $newClient --host=127.0.0.1 --port=3306 --user=root `
        --execute="DROP DATABASE IF EXISTS ``$Database``; CREATE DATABASE ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($LASTEXITCODE -ne 0) {
        throw "Laragon could not create the $Database database."
    }

    Write-Host "Importing the CRM records into Laragon..."
    $importArguments = @(
        "--host=127.0.0.1",
        "--port=3306",
        "--user=root",
        $Database
    )
    $importProcess = Start-Process -FilePath $newClient `
        -ArgumentList $importArguments `
        -RedirectStandardInput $dumpFile `
        -RedirectStandardOutput $importOutput `
        -RedirectStandardError $importError `
        -Wait `
        -PassThru

    if ($importProcess.ExitCode -ne 0) {
        $details = Get-Content $importError -Raw -ErrorAction SilentlyContinue
        throw "The SQL import failed. The original XAMPP data remains untouched.`n$details"
    }

    $tableCount = & $newClient --host=127.0.0.1 --port=3306 --user=root --batch --skip-column-names `
        --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$Database';"
    if ($LASTEXITCODE -ne 0 -or [int]$tableCount -eq 0) {
        throw "The import finished without any visible tables."
    }

    Write-Host "Migration completed successfully: $tableCount tables imported."
    Write-Host "SQL backup saved at: $dumpFile"
} finally {
    if ($recoveryServer -and -not $recoveryServer.HasExited) {
        Stop-Process -Id $recoveryServer.Id -Force -ErrorAction SilentlyContinue
    }
}
