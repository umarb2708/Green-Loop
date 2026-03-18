param(
    [switch]$InstallPhpHint
)

$ErrorActionPreference = "Stop"

Write-Host "Checking cloudflared installation..."
$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cloudflared) {
    Write-Host "cloudflared not found. Installing with winget..."
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget) {
        Write-Host "winget is not available on this machine."
        Write-Host "Install cloudflared manually from: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/"
        exit 1
    }

    winget install --id Cloudflare.cloudflared -e --accept-source-agreements --accept-package-agreements
}

Write-Host "Verifying cloudflared..."
cloudflared --version

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Host "PHP CLI was not found in PATH."
    if ($InstallPhpHint) {
        Write-Host "You can install PHP quickly with: winget install --id PHP.PHP -e"
    }
} else {
    Write-Host "PHP found: $($php.Source)"
    php -v | Select-Object -First 1
}

Write-Host "Done. Next run: .\\tools\\start_https_tunnel.ps1"
