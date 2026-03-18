param(
    [int]$Port = 8080,
    [string]$SitePath = "",
    [switch]$UseExistingServer,
    [string]$TargetUrl = ""
)

$ErrorActionPreference = "Stop"

$phpProc = $null

function Test-LocalPortOpen {
    param([int]$ProbePort)

    try {
        return (Test-NetConnection -ComputerName "localhost" -Port $ProbePort -InformationLevel Quiet -WarningAction SilentlyContinue)
    }
    catch {
        return $false
    }
}

function Get-LocalUrlFromPort {
    param([int]$ProbePort)

    if ($ProbePort -eq 80) {
        return "http://localhost"
    }

    return "http://localhost:$ProbePort"
}

function Start-PhpServer {
    param(
        [string]$RootPath,
        [int]$ListenPort
    )

    Write-Host "Starting PHP server from: $RootPath"
    Write-Host "Local URL: $(Get-LocalUrlFromPort -ProbePort $ListenPort)"

    $phpArgs = @("-S", "0.0.0.0:$ListenPort", "-t", "$RootPath")
    return (Start-Process -FilePath "php" -ArgumentList $phpArgs -PassThru)
}

$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cloudflared) {
    Write-Host "cloudflared is not installed. Run .\\tools\\install_cloudflared.ps1 first."
    exit 1
}

if ([string]::IsNullOrWhiteSpace($TargetUrl)) {
    if ($UseExistingServer -or (Test-LocalPortOpen -ProbePort $Port)) {
        $TargetUrl = Get-LocalUrlFromPort -ProbePort $Port
        Write-Host "Using existing web server at: $TargetUrl"
        Write-Host "Tip: For XAMPP subfolder apps use -TargetUrl http://localhost/smart_bin_qr"
    }
    else {
        $php = Get-Command php -ErrorAction SilentlyContinue
        if (-not $php) {
            Write-Host "PHP CLI is not in PATH."
            Write-Host "Either install PHP CLI, or run with existing server mode:"
            Write-Host ".\\tools\\start_https_tunnel.ps1 -UseExistingServer -TargetUrl http://localhost/smart_bin_qr"
            exit 1
        }

        if ([string]::IsNullOrWhiteSpace($SitePath)) {
            $SitePath = Resolve-Path (Join-Path $PSScriptRoot "..")
        }
        else {
            $SitePath = Resolve-Path $SitePath
        }

        $phpProc = Start-PhpServer -RootPath $SitePath -ListenPort $Port
        Write-Host "PHP started. PID: $($phpProc.Id)"
        $TargetUrl = Get-LocalUrlFromPort -ProbePort $Port
    }
}
else {
    Write-Host "Using explicit target URL: $TargetUrl"
}

if ($TargetUrl -notmatch '^https?://') {
    Write-Host "TargetUrl must start with http:// or https://"
    exit 1
}

Write-Host "Starting Cloudflare tunnel now..."
Write-Host "When tunnel URL appears, open it on Android with HTTPS."
Write-Host "Tunnel source: $TargetUrl"
Write-Host "Press Ctrl+C to stop cloudflared."
if ($phpProc) {
    Write-Host "PHP started by this script will be stopped automatically."
}

try {
    cloudflared tunnel --url "$TargetUrl"
}
finally {
    if ($phpProc -and -not $phpProc.HasExited) {
        Stop-Process -Id $phpProc.Id -Force
        Write-Host "Stopped PHP process $($phpProc.Id)"
    }
}
