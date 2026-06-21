$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$token = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$body = @{
    _token = $token
    username = "gracious"
    password = "user123"
}

Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5 | Out-Null
$dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard?modern=1" -WebSession $session
$css = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/templates/indigo_fusion/css/custom.css?modern=1"

[pscustomobject]@{
    DashboardStatus = $dashboard.StatusCode
    HasNatdash = $dashboard.Content.Contains('class="container natdash"')
    HasProfile = $dashboard.Content.Contains('natdash-profile')
    HasBalance = $dashboard.Content.Contains('natdash-balance')
    HasActions = $dashboard.Content.Contains('natdash-actions')
    HasManageProfile = $dashboard.Content.Contains('Manage profile')
    CssStatus = $css.StatusCode
    HasDashboardCss = $css.Content.Contains('NATCODEV professional member dashboard')
    HasNavCss = $css.Content.Contains('NATCODEV professional top navigation')
}
