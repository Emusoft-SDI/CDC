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

$deposit = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/deposit"

[pscustomobject]@{
    DepositStatus = [int]$deposit.StatusCode
    HasTopbar = $deposit.Content.Contains('nat-member-topbar')
    HasMemberNav = $deposit.Content.Contains('nat-member-nav')
    HasBreadcrumbs = $deposit.Content.Contains('nat-member-crumbs')
    HasNextActions = $deposit.Content.Contains('nat-member-next-actions')
    HasDashboardLink = $deposit.Content.Contains('/user/dashboard')
    HasTransactionsLink = $deposit.Content.Contains('/user/transactions')
}
