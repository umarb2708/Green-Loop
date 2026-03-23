# Green Loop - Install Cloudflared
# This script downloads and installs cloudflared on Windows

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Green Loop - Install Cloudflared" -ForegroundColor Cyan
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

# Define paths
$installPath = "$env:ProgramFiles\cloudflared"
$exePath = "$installPath\cloudflared.exe"
$downloadUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"

Write-Host "Step 1: Creating installation directory..." -ForegroundColor Yellow
if (-not (Test-Path $installPath)) {
    New-Item -ItemType Directory -Path $installPath -Force | Out-Null
    Write-Host "  Created: $installPath" -ForegroundColor Green
} else {
    Write-Host "  Directory already exists" -ForegroundColor Green
}

Write-Host ""
Write-Host "Step 2: Downloading cloudflared..." -ForegroundColor Yellow
try {
    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $downloadUrl -OutFile $exePath
    Write-Host "  Downloaded successfully" -ForegroundColor Green
} catch {
    Write-Host "  ERROR: Failed to download cloudflared" -ForegroundColor Red
    Write-Host "  $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Step 3: Adding to PATH..." -ForegroundColor Yellow
$currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
if ($currentPath -notlike "*$installPath*") {
    [Environment]::SetEnvironmentVariable("Path", "$currentPath;$installPath", "Machine")
    Write-Host "  Added to system PATH" -ForegroundColor Green
} else {
    Write-Host "  Already in PATH" -ForegroundColor Green
}

Write-Host ""
Write-Host "Step 4: Verifying installation..." -ForegroundColor Yellow
try {
    $version = & $exePath --version
    Write-Host "  Installed: $version" -ForegroundColor Green
} catch {
    Write-Host "  WARNING: Could not verify installation" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Installation Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Restart your terminal to use cloudflared" -ForegroundColor White
Write-Host "2. Run start_https_tunnel.ps1 to start the tunnel" -ForegroundColor White
Write-Host ""

Read-Host "Press Enter to exit"
