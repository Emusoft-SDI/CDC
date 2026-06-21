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
$transactions = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/transactions"

[pscustomobject]@{
    DashboardStatus = [int]$dashboard.StatusCode
    TransactionsStatus = [int]$transactions.StatusCode
    HasMemberTopbar = $dashboard.Content.Contains('nat-member-topbar')
    HasModernNav = $dashboard.Content.Contains('nat-member-nav')
    HasProfileChip = $dashboard.Content.Contains('nat-profile-chip')
    HasCompactPageTitle = $dashboard.Content.Contains('nat-member-page-title')
    HasDashboardActive = $dashboard.Content.Contains('href="http://localhost/cocopay/user/dashboard"') -or $dashboard.Content.Contains('Dashboard')
    HasOldHeroClassOnly = (-not $dashboard.Content.Contains('inner-hero bg_img overlay--one'))
    TransactionsHasSecondaryTabs = $transactions.Content.Contains('bottom-menu-section')
}
