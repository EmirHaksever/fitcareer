[CmdletBinding()]
param(
    [switch]$Remove
)

$TaskName = 'FitCareer - Job Automation'
$RunnerPath = Join-Path (Split-Path -Parent $PSScriptRoot) 'scripts\run-fitcareer-workers.ps1'
$TaskRun = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$RunnerPath`""
$StartupPath = [Environment]::GetFolderPath('Startup')
$ShortcutPath = Join-Path $StartupPath 'FitCareer - Job Automation.lnk'

function Install-UserStartupShortcut {
    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($ShortcutPath)
    $shortcut.TargetPath = (Get-Command powershell.exe).Source
    $shortcut.Arguments = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$RunnerPath`""
    $shortcut.WorkingDirectory = (Split-Path -Parent $RunnerPath)
    $shortcut.WindowStyle = 7
    $shortcut.Description = 'FitCareer scheduler and queue worker supervisor'
    $shortcut.Save()
}

if ($Remove) {
    & schtasks.exe /Delete /TN $TaskName /F 2>$null | Out-Null
    if (Test-Path -LiteralPath $ShortcutPath) {
        Remove-Item -LiteralPath $ShortcutPath -Force
    }
    Write-Output "Removed: $TaskName"
    Write-Output "Removed user startup shortcut: $ShortcutPath"
    exit 0
}

if (-not (Test-Path -LiteralPath $RunnerPath)) {
    throw "Automation runner not found: $RunnerPath"
}

& schtasks.exe /Create /TN $TaskName /SC ONLOGON /TR $TaskRun /F 2>$null | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Output "Registered: $TaskName"
    Write-Output "The scheduler and queue worker will start automatically when this Windows user logs in."
    exit 0
}

Install-UserStartupShortcut
Write-Output "Task Scheduler requires elevation on this machine."
Write-Output "Registered user startup shortcut: $ShortcutPath"
Write-Output "The scheduler and queue worker will start automatically when this Windows user logs in."
