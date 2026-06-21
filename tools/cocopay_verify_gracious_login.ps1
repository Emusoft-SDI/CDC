function Test-CocopayLogin {
    param(
        [string]$Login
    )

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
    $token = [regex]::Match($loginPage.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

    $body = @{
        _token = $token
        username = $Login
        password = "user123"
    }

    $post = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5
    $dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard" -WebSession $session -MaximumRedirection 0

    [pscustomobject]@{
        Login = $Login
        PostStatus = $post.StatusCode
        DashboardStatus = $dashboard.StatusCode
        ReachedDashboard = $dashboard.Content.Contains("Welcome back")
        HasProfile = $dashboard.Content.Contains("natdash-profile")
    }
}

Test-CocopayLogin -Login "gracious"
Test-CocopayLogin -Login "altarofpraisenworship@gmail.com"
