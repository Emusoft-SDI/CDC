$register = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/register"
$lgas = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/user/register/lgas/25"
$css = Invoke-WebRequest -UseBasicParsing "http://localhost/cocopay/assets/templates/indigo_fusion/css/custom.css?location-ui=1"

[pscustomobject]@{
    RegisterStatus = $register.StatusCode
    HasNigeriaDefault = $register.Content.Contains('data-code="NG"')
    HasStateField = $register.Content.Contains('name="state_id"')
    HasLgaField = $register.Content.Contains('name="lga_id"')
    HasLgaRoute = $register.Content.Contains('/user/register/lgas')
    LgaStatus = $lgas.StatusCode
    HasLagosIsland = $lgas.Content.Contains('Lagos Island')
    HasEpe = $lgas.Content.Contains('Epe')
    CssStatus = $css.StatusCode
    HasCalmerFooter = $css.Content.Contains('NATCODEV calmer navigation and footer')
    HasMenuFix = $css.Content.Contains('.main-menu li a')
}
