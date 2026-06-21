$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$token = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$body = @{
    _token = $token
    username = "demo_user"
    password = "user123"
}

$post = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5
$dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard" -WebSession $session

[pscustomobject]@{
    LoginStatus = $post.StatusCode
    DashboardStatus = $dashboard.StatusCode
    HasNatcodevHero = $dashboard.Content.Contains("NATCODEV member workspace")
    HasLoanAction = $dashboard.Content.Contains("Apply Farm Loan")
    Length = $dashboard.Content.Length
}
