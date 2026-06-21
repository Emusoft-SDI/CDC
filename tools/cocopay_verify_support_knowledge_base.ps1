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
$kb = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/support/knowledge-base"

[pscustomobject]@{
    DashboardStatus = [int]$dashboard.StatusCode
    KnowledgeBaseStatus = [int]$kb.StatusCode
    HeaderHasSupport = $dashboard.Content.Contains('supportMenu')
    HeaderHasKnowledgeBase = $dashboard.Content.Contains('/user/support/knowledge-base')
    HeaderHasOpenTicket = $dashboard.Content.Contains('/ticket/new')
    KbHasHero = $kb.Content.Contains('Knowledge Base')
    KbHasFaqs = $kb.Content.Contains('Wallet &amp; Transactions') -or $kb.Content.Contains('Wallet & Transactions')
    KbHasOpenTicket = $kb.Content.Contains('/ticket/new')
}
