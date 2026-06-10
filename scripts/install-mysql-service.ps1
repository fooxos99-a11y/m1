$ErrorActionPreference = 'Stop'

$serviceName = 'MySQLMomars'
$mysqlExe = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe'
$dataDir = 'C:\Users\RAIQ\AppData\Local\MySQL\momars-data'
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())

if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'This script must be run from an elevated VS Code window opened as Administrator.'
}

if (-not (Test-Path $mysqlExe)) {
    throw 'MySQL server executable was not found. Install MySQL first.'
}

if (-not (Test-Path $dataDir)) {
    throw 'MySQL data directory was not found. Initialize/import MySQL first.'
}

$existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue

if (-not $existing) {
    $binPath = '"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --no-defaults --basedir="C:\Program Files\MySQL\MySQL Server 8.4" --datadir="C:\Users\RAIQ\AppData\Local\MySQL\momars-data" --port=3306 --mysqlx=0 --bind-address=127.0.0.1 --lc-messages-dir="C:\Program Files\MySQL\MySQL Server 8.4\share" --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci'
    sc.exe create $serviceName binPath= $binPath start= auto | Out-Host
    sc.exe description $serviceName "Momars local MySQL 8.4 service" | Out-Host
}

Start-Service -Name $serviceName

for ($attempt = 0; $attempt -lt 20; $attempt++) {
    Start-Sleep -Milliseconds 500

    if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
        Write-Host 'MySQL service is installed and running on port 3306.'
        exit 0
    }
}

throw 'MySQL service was created but did not start listening on port 3306.'