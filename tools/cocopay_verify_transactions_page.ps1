$ErrorActionPreference = 'Stop'

$base = 'http://localhost/cocopay'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$loginPage = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/login"
$token = [regex]::Match($loginPage.Content, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
if (-not $token) {
    $token = [regex]::Match($loginPage.Content, 'value="([^"]+)"\s+name="_token"').Groups[1].Value
}
if (-not $token) {
    throw 'Unable to find CSRF token on login page.'
}

$body = @{
    _token = $token
    username = 'gracious'
    password = 'user123'
}

Invoke-WebRequest -UseBasicParsing -WebSession $session -Method Post -Body $body "$base/user/login" | Out-Null

$page = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/transactions"
$credit = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/transactions?trx_type=%2B"

[pscustomobject]@{
    TransactionsStatus = [int]$page.StatusCode
    CreditFilterStatus = [int]$credit.StatusCode
    HasMoneyInLabel = $page.Content.Contains('Money In')
    HasMoneyOutLabel = $page.Content.Contains('Money Out')
    HasViewButton = $page.Content.Contains('data-bs-target="#transactionDetailModal"')
    HasDetailModal = $page.Content.Contains('Transaction Detail')
    HasCreditFilter = $credit.Content.Contains('Money In')
    HasRawPlusOption = $page.Content.Contains('>Plus<')
    HasRawMinusOption = $page.Content.Contains('>Minus<')
}
