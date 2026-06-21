$ErrorActionPreference = 'Stop'

$base = 'http://localhost/cocopay'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$register = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/register"

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

$profile = Invoke-WebRequest -UseBasicParsing -WebSession $session "$base/user/profile-setting"

[pscustomobject]@{
    RegisterStatus = [int]$register.StatusCode
    RegisterHasMultipart = $register.Content.Contains('enctype="multipart/form-data"')
    RegisterHasCertificateInput = $register.Content.Contains('name="grower_certificate"')
    RegisterMentionsNatcodevCertificate = $register.Content.Contains('NATCODEV Growers Certificate')
    ProfileStatus = [int]$profile.StatusCode
    ProfileHasCertificateStatus = $profile.Content.Contains('Membership Certificate')
    ProfileHasCertificateUpload = $profile.Content.Contains('name="grower_certificate"')
}
