# Green Loop - Start HTTPS Tunnel
# This script starts a cloudflared tunnel to expose the local server via HTTPS

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Green Loop - HTTPS Tunnel" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if cloudflared is installed
$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue

if (-not $cloudflared) {
    Write-Host "ERROR: cloudflared is not installed!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please run install_cloudflared.ps1 first" -ForegroundColor Yellow
    Write-Host "Or install manually from: https://github.com/cloudflare/cloudflared/releases" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}

# Configuration
$localPort = 80  # Change this if your local server runs on a different port
$localHost = "localhost:$localPort"

Write-Host "Configuration:" -ForegroundColor Yellow
Write-Host "  Local Server: http://$localHost" -ForegroundColor White
Write-Host "  Tunnel Mode: HTTP over HTTPS" -ForegroundColor White
Write-Host ""

Write-Host "Starting tunnel..." -ForegroundColor Yellow
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Tunnel Information (below)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Start the tunnel
# The tunnel will display a public HTTPS URL that can be used to access your local server
cloudflared tunnel --url http://$localHost

# This line will only execute if cloudflared exits/fails
Write-Host ""
Write-Host "Tunnel stopped." -ForegroundColor Yellow
Read-Host "Press Enter to exit"
