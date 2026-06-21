$ErrorActionPreference = 'Stop'

$base = 'http://localhost/cocopay'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$loginPage = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/login"
$token = [regex]::Match($loginPage.Content, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
if (-not $token) {
    $token = [regex]::Match($loginPage.Content, 'value="([^"]+)"\s+name="_token"').Groups[1].Value
}
if (-not $token) {
    throw 'Unable to find CSRF token.'
}

Invoke-WebRequest -UseBasicParsing -WebSession $session -Method Post -Body @{
    _token = $token
    username = 'gracious'
    password = 'user123'
} "$base/user/login" | Out-Null

$dashboard = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/dashboard"
$logo = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/assets/images/logoIcon/logo.png"

[pscustomobject]@{
    DashboardStatus = [int]$dashboard.StatusCode
    LogoAssetStatus = [int]$logo.StatusCode
    UsesLogoPng = $dashboard.Content.Contains('/assets/images/logoIcon/logo.png')
    UsesLogoSvg = $dashboard.Content.Contains('/assets/images/logoIcon/logo.svg')
    LogoBytes = $logo.RawContentLength
}
