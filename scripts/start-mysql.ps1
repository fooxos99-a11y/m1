$ErrorActionPreference = 'Stop'

$mysqlExe = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe'
$workingDir = 'C:\Program Files\MySQL\MySQL Server 8.4\bin'
$dataDir = 'C:\Users\RAIQ\AppData\Local\MySQL\momars-data'
$serviceName = 'MySQLMomars'

if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
    Write-Host 'MySQL is already listening on port 3306.'
    exit 0
}

$service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue

if ($service) {
    if ($service.Status -ne 'Running') {
        Start-Service -Name $serviceName
    }

    Start-Sleep -Seconds 2

    if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
        Write-Host 'MySQL service started on port 3306.'
        exit 0
    }

    throw 'MySQL service exists but did not start listening on port 3306.'
}

if (-not (Test-Path $mysqlExe)) {
    throw 'MySQL server executable was not found. Install MySQL first.'
}

if (-not (Test-Path $dataDir)) {
    throw 'MySQL data directory was not found. Initialize MySQL before starting it.'
}

$arguments = @(
    '--no-defaults',
    '--standalone',
    '--basedir="C:\Program Files\MySQL\MySQL Server 8.4"',
    '--datadir="C:\Users\RAIQ\AppData\Local\MySQL\momars-data"',
    '--port=3306',
    '--mysqlx=0',
    '--bind-address=127.0.0.1',
    '--lc-messages-dir="C:\Program Files\MySQL\MySQL Server 8.4\share"',
    '--character-set-server=utf8mb4',
    '--collation-server=utf8mb4_unicode_ci'
)

$existing = Get-CimInstance Win32_Process -Filter "Name = 'mysqld.exe'" -ErrorAction SilentlyContinue | Where-Object {
    $_.CommandLine -like '*momars-data*'
}

if ($existing) {
    Write-Host 'MySQL process is already running for this workspace.'
    exit 0
}

Start-Process -FilePath $mysqlExe -ArgumentList $arguments -WorkingDirectory $workingDir -WindowStyle Hidden | Out-Null

for ($attempt = 0; $attempt -lt 20; $attempt++) {
    Start-Sleep -Milliseconds 500

    if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
        Write-Host 'MySQL started in background on port 3306.'
        exit 0
    }
}

throw 'MySQL did not start listening on port 3306.'