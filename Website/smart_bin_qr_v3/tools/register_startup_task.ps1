# Green Loop - Register Startup Task
# This script creates a Windows Task Scheduler entry to start the tunnel on system startup

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Green Loop - Register Startup Task" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Task configuration
$taskName = "GreenLoopTunnel"
$taskDescription = "Starts Cloudflare tunnel for Green Loop on system startup"
$scriptPath = "$PSScriptRoot\start_https_tunnel.ps1"

# Check if script exists
if (-not (Test-Path $scriptPath)) {
    Write-Host "ERROR: start_https_tunnel.ps1 not found!" -ForegroundColor Red
    Write-Host "Expected location: $scriptPath" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "Creating scheduled task..." -ForegroundColor Yellow
Write-Host "  Task Name: $taskName" -ForegroundColor White
Write-Host "  Description: $taskDescription" -ForegroundColor White
Write-Host ""

try {
    # Check if task already exists
    $existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    
    if ($existingTask) {
        Write-Host "Task already exists. Removing old task..." -ForegroundColor Yellow
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    }
    
    # Create action
    $action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`""
    
    # Create trigger (at startup)
    $trigger = New-ScheduledTaskTrigger -AtStartup
    
    # Create settings
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
    
    # Create principal (run with highest privileges)
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    
    # Register task
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger `
        -Settings $settings -Principal $principal -Description $taskDescription | Out-Null
    
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Task registered successfully!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "The tunnel will now start automatically on system startup." -ForegroundColor Green
    Write-Host ""
    Write-Host "To manage the task:" -ForegroundColor Yellow
    Write-Host "1. Open Task Scheduler (taskschd.msc)" -ForegroundColor White
    Write-Host "2. Look for '$taskName' in Task Scheduler Library" -ForegroundColor White
    Write-Host ""
    Write-Host "To remove the task, run:" -ForegroundColor Yellow
    Write-Host "  Unregister-ScheduledTask -TaskName '$taskName' -Confirm:`$false" -ForegroundColor White
    Write-Host ""
    
} catch {
    Write-Host "ERROR: Failed to create scheduled task" -ForegroundColor Red
    Write-Host "$_" -ForegroundColor Red
}

Read-Host "Press Enter to exit"
