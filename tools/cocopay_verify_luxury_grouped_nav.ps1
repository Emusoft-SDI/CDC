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
    HasLuxuryTopbar = $dashboard.Content.Contains('nat-lux-topbar')
    HasGroupedNav = $dashboard.Content.Contains('nat-lux-nav')
    HasWalletMenu = $dashboard.Content.Contains('Wallet')
    HasGrowthMenu = $dashboard.Content.Contains('Growth')
    HasServicesMenu = $dashboard.Content.Contains('Services')
    HasAirtimeBills = $dashboard.Content.Contains('Airtime &amp; Bills') -or $dashboard.Content.Contains('Airtime & Bills')
    HasOldTopAirtimeItem = $dashboard.Content.Contains('<span>Airtime</span>')
}
