param(
    [ValidateSet("register", "unregister")]
    [string]$Mode = "register",
    [int]$Port = 8080,
    [switch]$UseExistingServer,
    [string]$TargetUrl = ""
)

$ErrorActionPreference = "Stop"
$taskName = "GreenLoopHttpsTunnel"
$scriptPath = Resolve-Path (Join-Path $PSScriptRoot "start_https_tunnel.ps1")

if ($Mode -eq "unregister") {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Removed task: $taskName"
    exit 0
}

$pwsh = (Get-Command powershell.exe).Source
$argParts = @(
    "-NoProfile",
    "-ExecutionPolicy", "Bypass",
    "-File", "`"$scriptPath`"",
    "-Port", "$Port"
)

if ($UseExistingServer) {
    $argParts += "-UseExistingServer"
}

if (-not [string]::IsNullOrWhiteSpace($TargetUrl)) {
    $argParts += "-TargetUrl"
    $argParts += "`"$TargetUrl`""
}

$arg = $argParts -join " "

$action = New-ScheduledTaskAction -Execute $pwsh -Argument $arg
$trigger = New-ScheduledTaskTrigger -AtLogOn
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel LeastPrivilege
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -StartWhenAvailable

$task = New-ScheduledTask -Action $action -Trigger $trigger -Principal $principal -Settings $settings
Register-ScheduledTask -TaskName $taskName -InputObject $task -Force | Out-Null

Write-Host "Created/updated task: $taskName"
Write-Host "It starts the Cloudflare tunnel at logon."
if ($UseExistingServer -or (-not [string]::IsNullOrWhiteSpace($TargetUrl))) {
    Write-Host "Configured to use your existing web server (XAMPP/IIS/etc)."
}
else {
    Write-Host "Configured to launch PHP built-in server when needed."
}
Write-Host "To remove it later: .\\tools\\register_startup_task.ps1 -Mode unregister"
