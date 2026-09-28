[CmdletBinding()]
param(
    [switch]$Stop
)

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PhpPath = 'C:\xampp\php\php.exe'
$LogPath = Join-Path $ProjectRoot 'storage\logs\automation-supervisor.log'

if (-not (Test-Path -LiteralPath $PhpPath)) {
    $PhpPath = (Get-Command php.exe -ErrorAction Stop).Source
}

function Write-SupervisorLog([string]$Message) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -LiteralPath $LogPath -Value $line -Encoding utf8
}

function Get-FitCareerProcess([string]$Pattern) {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -and $_.CommandLine -like "*$Pattern*" }
}

function Start-FitCareerProcess([string[]]$Arguments, [string]$Label) {
    $argumentList = @('-d', 'variables_order=GPCS') + $Arguments
    Start-Process -WindowStyle Hidden -FilePath $PhpPath -ArgumentList $argumentList -WorkingDirectory $ProjectRoot | Out-Null
    Write-SupervisorLog("Started $Label")
}

if ($Stop) {
    $patterns = @('artisan schedule:work', 'artisan queue:work database')
    foreach ($pattern in $patterns) {
        Get-FitCareerProcess $pattern | ForEach-Object {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
            Write-SupervisorLog("Stopped $pattern ($($_.ProcessId))")
        }
    }

    exit 0
}

New-Item -ItemType Directory -Path (Split-Path -Parent $LogPath) -Force | Out-Null
Write-SupervisorLog('FitCareer automation supervisor started.')

while ($true) {
    if (-not (Get-FitCareerProcess 'artisan schedule:work')) {
        Start-FitCareerProcess @('artisan', 'schedule:work') 'scheduler'
    }

    if (-not (Get-FitCareerProcess 'artisan queue:work database')) {
        Start-FitCareerProcess @('artisan', 'queue:work', 'database', '--queue=default', '--sleep=3', '--tries=3', '--timeout=120') 'queue worker'
    }

    Start-Sleep -Seconds 20
}
