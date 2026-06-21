$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$token = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$body = @{
    _token = $token
    username = "demo_user"
    password = "user123"
}

Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5 | Out-Null
$dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard?rendercheck=1" -WebSession $session
$css = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/templates/indigo_fusion/css/custom.css?rendercheck=1"
$logo = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/images/logoIcon/logo.png?rendercheck=1"

[pscustomobject]@{
    DashboardStatus = $dashboard.StatusCode
    HasContainer = $dashboard.Content.Contains("natco-dashboard-page")
    HasHero = $dashboard.Content.Contains("natco-member-hero")
    HasVersionedCustomCss = $dashboard.Content.Contains("custom.css?v=")
    HasVersionedLogo = $dashboard.Content.Contains("logo.png?v=")
    CssStatus = $css.StatusCode
    CssHasNatcoHero = $css.Content.Contains("natco-member-hero")
    CssHasRenderedFix = $css.Content.Contains("NATCODEV rendered design fix")
    LogoStatus = $logo.StatusCode
    LogoLength = $logo.RawContentLength
}
