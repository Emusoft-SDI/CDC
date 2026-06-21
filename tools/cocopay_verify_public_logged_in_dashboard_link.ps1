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

$services = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/services"

[pscustomobject]@{
    ServicesStatus = [int]$services.StatusCode
    HasDashboardButton = $services.Content.Contains('nat-public-dashboard-btn')
    HasMemberChip = $services.Content.Contains('nat-public-member-chip')
    HasDashboardHref = $services.Content.Contains('/user/dashboard')
    HasTransactionsHref = $services.Content.Contains('/user/transactions')
    HasLogout = $services.Content.Contains('/user/logout')
}
