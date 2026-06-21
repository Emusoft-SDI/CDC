$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$token = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$body = @{
    _token = $token
    username = "gracious"
    password = "user123"
}

$loginResponse = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $body -WebSession $session -MaximumRedirection 5
$dashboard = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/dashboard" -WebSession $session -MaximumRedirection 0
$download = $null
$downloadError = $null
try {
    $download = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/loan/download/1?pdfcheck=1" -WebSession $session -MaximumRedirection 0
} catch {
    $downloadError = $_
    $download = $_.Exception.Response
}

[pscustomobject]@{
    LoginStatus = $loginResponse.StatusCode
    DashboardStatus = $dashboard.StatusCode
    LoginReachedDashboard = $dashboard.Content.Contains("Welcome back")
    DownloadStatus = [int]$download.StatusCode
    Location = $download.Headers.Location
    ContentType = $download.Headers.'Content-Type'
    Disposition = $download.Headers.'Content-Disposition'
    Length = $download.RawContentLength
    Error = if ($downloadError) { $downloadError.Exception.Message } else { $null }
}
