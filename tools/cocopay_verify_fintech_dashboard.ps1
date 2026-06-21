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

[pscustomobject]@{
    DashboardStatus = [int]$dashboard.StatusCode
    HasFintechGrid = $dashboard.Content.Contains('natfin-grid')
    HasWalletCard = $dashboard.Content.Contains('natfin-wallet-card')
    HasSideNav = $dashboard.Content.Contains('natfin-side-nav')
    HasActivityList = $dashboard.Content.Contains('natfin-activity-list')
    HasRightRail = $dashboard.Content.Contains('natfin-right')
    HasOldNatdashShell = $dashboard.Content.Contains('natdash-shell')
}
