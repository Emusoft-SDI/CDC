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

Invoke-WebRequest -UseBasicParsing -WebSession $session -Method Post -Body @{
    _token = $token
    username = 'gracious'
    password = 'user123'
} "$base/user/login" | Out-Null

function Get-Page($Url) {
    try {
        return Invoke-WebRequest -UseBasicParsing -WebSession $session -MaximumRedirection 10 $Url
    } catch {
        if ($_.Exception.Response) {
            return $_.Exception.Response
        }
        throw
    }
}

$depositIndex = Get-Page "$base/user/deposit"
$depositManual = Get-Page "$base/user/deposit/manual"

[pscustomobject]@{
    DepositIndexStatus = [int]$depositIndex.StatusCode
    DepositManualStatus = [int]$depositManual.StatusCode
    MissingViserFormError = ($depositIndex.Content + $depositManual.Content).Contains('ViserForm.php')
    MissingViserFormDataError = ($depositIndex.Content + $depositManual.Content).Contains('viser-form-data')
}
