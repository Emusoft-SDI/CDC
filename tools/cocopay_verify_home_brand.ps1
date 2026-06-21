$homeResponse = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/"

[pscustomobject]@{
    Status = $homeResponse.StatusCode
    HasLogoReference = $homeResponse.Content.Contains("logo.png")
    HasCustomCss = $homeResponse.Content.Contains("custom.css")
    HasNatcodev = $homeResponse.Content.Contains("NATCODEV")
    Length = $homeResponse.Content.Length
}
