<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';

$appLayout = $base . '/core/resources/views/templates/indigo_fusion/layouts/app.blade.php';
$app = file_get_contents($appLayout);
$old = <<<'BLADE'
    <link href="{{ asset($activeTemplateTrue . 'css/main.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/custom.css') }}" rel="stylesheet">
    @stack('style-lib')
    @stack('style')
    <link href="{{ asset($activeTemplateTrue . 'css/color.php?color=' . $general->base_color . '&secondColor=' . $general->secondary_color) }}" rel="stylesheet">
BLADE;
$new = <<<'BLADE'
    <link href="{{ asset($activeTemplateTrue . 'css/main.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/color.php?color=' . $general->base_color . '&secondColor=' . $general->secondary_color) }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/custom.css') }}?v={{ @filemtime(public_path($activeTemplateTrue . 'css/custom.css')) }}" rel="stylesheet">
    @stack('style-lib')
    @stack('style')
BLADE;
if (strpos($app, $old) !== false) {
    $app = str_replace($old, $new, $app);
    file_put_contents($appLayout, $app);
}

$helper = $base . '/core/app/Http/Helpers/helpers.php';
$helpers = file_get_contents($helper);
$oldLogo = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.png" : '/logo.png';
    return getImage(getFilePath('logoIcon') . $name);
}

function siteFavicon()
{
    return getImage(getFilePath('logoIcon') . '/favicon.png');
}
PHP;
$newLogo = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.png" : '/logo.png';
    $path = getFilePath('logoIcon') . $name;
    $version = @filemtime(public_path($path)) ?: time();
    return getImage($path) . '?v=' . $version;
}

function siteFavicon()
{
    $path = getFilePath('logoIcon') . '/favicon.png';
    $version = @filemtime(public_path($path)) ?: time();
    return getImage($path) . '?v=' . $version;
}
PHP;
if (strpos($helpers, $oldLogo) !== false) {
    $helpers = str_replace($oldLogo, $newLogo, $helpers);
    file_put_contents($helper, $helpers);
}

$dashboard = $base . '/core/resources/views/templates/indigo_fusion/user/dashboard.blade.php';
$dash = file_get_contents($dashboard);
if (strpos($dash, 'natco-dashboard-page') === false) {
    $dash = str_replace("@section('content')\n", "@section('content')\n    <div class=\"container natco-dashboard-page\">\n", $dash);
    $dash = str_replace("\n@endsection\n\n@push('script')", "\n    </div>\n@endsection\n\n@push('script')", $dash);
    file_put_contents($dashboard, $dash);
}

$loanForm = $base . '/core/resources/views/templates/indigo_fusion/user/loan/form.blade.php';
$loan = file_get_contents($loanForm);
if (strpos($loan, 'natco-loan-page') === false) {
    $loan = str_replace("@section('content')\n", "@section('content')\n    <div class=\"container natco-loan-page\">\n", $loan);
    $loan = str_replace("\n@endsection\n\n@push('bottom-menu')", "\n    </div>\n@endsection\n\n@push('bottom-menu')", $loan);
    file_put_contents($loanForm, $loan);
}

$cssFile = $base . '/assets/templates/indigo_fusion/css/custom.css';
$css = file_get_contents($cssFile);
$marker = '/* NATCODEV rendered design fix */';
if (strpos($css, $marker) === false) {
    $css .= <<<'CSS'

/* NATCODEV rendered design fix */
.natco-dashboard-page,
.natco-loan-page {
    max-width: 1180px;
}
.natco-dashboard-page a,
.natco-loan-page a {
    text-decoration: none;
}
.natco-dashboard-page .natco-member-hero {
    margin-top: -20px;
}
.main-wrapper > .pt-100 {
    background-color: #f6faf2;
}
.inner-hero {
    min-height: 220px;
}
.site-logo {
    align-items: center;
    display: inline-flex;
    min-width: 260px;
}
.site-logo img {
    display: block;
}
.footer__bottom {
    background: rgba(255, 255, 255, .04);
}
CSS;
    file_put_contents($cssFile, $css);
}

echo "Fixed rendered design containment, CSS ordering, and asset cache busting." . PHP_EOL;

