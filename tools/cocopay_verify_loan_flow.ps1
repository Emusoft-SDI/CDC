$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -WebSession $session
$loginToken = [regex]::Match($login.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$loginBody = @{
    _token = $loginToken
    username = "demo_user"
    password = "user123"
}

Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/login" -Method Post -Body $loginBody -WebSession $session -MaximumRedirection 5 | Out-Null

$plans = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/loan/plans" -WebSession $session
$applyToken = [regex]::Match($plans.Content, 'name="_token" value="([^"]+)"').Groups[1].Value

$applyBody = @{
    _token = $applyToken
    amount = "1000"
}

Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/loan/apply/1" -Method Post -Body $applyBody -WebSession $session -MaximumRedirection 5 | Out-Null
$preview = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/loan/application-preview" -WebSession $session

[pscustomobject]@{
    PlansStatus = $plans.StatusCode
    PreviewStatus = $preview.StatusCode
    HasFarmHeading = $preview.Content.Contains("Complete Your Cooperative Loan Request")
    HasDwarfOption = $preview.Content.Contains("Dwarf Coconut")
    HasSubmit = $preview.Content.Contains("Submit Loan Request")
    Length = $preview.Content.Length
}
