$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$token = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$body = @{
    _token = $token
    username = "demo_user"
    password = "user123"
}

Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5 | Out-Null
$dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard?logo=default" -WebSession $session
$homeResponse = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/?logo=default"
$logo = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/images/logoIcon/logo.svg?logo=default"
$favicon = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/images/logoIcon/favicon.svg?logo=default"

[pscustomobject]@{
    DashboardStatus = $dashboard.StatusCode
    DashboardUsesSvgLogo = $dashboard.Content.Contains("logo.svg?v=")
    DashboardUsesOldPngLogo = $dashboard.Content.Contains("logo.png?v=")
    HomeStatus = $homeResponse.StatusCode
    HomeUsesSvgLogo = $homeResponse.Content.Contains("logo.svg?v=")
    LogoStatus = $logo.StatusCode
    LogoHasNatcodevText = $logo.Content.Contains("NATCODEV")
    FaviconStatus = $favicon.StatusCode
}
