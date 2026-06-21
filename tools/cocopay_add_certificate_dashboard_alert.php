<?php

$viewPath = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/indigo_fusion/user/dashboard.blade.php';
$cssPath = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/templates/indigo_fusion/css/custom.css';

if (!is_file($viewPath) || !is_file($cssPath)) {
    throw new RuntimeException('Dashboard view or CSS file missing.');
}

$view = file_get_contents($viewPath);
$needle = <<<'BLADE'
    <div class="container natfin">
        @if ($user->kv != Status::KYC_VERIFIED)
BLADE;
$insert = <<<'BLADE'
    <div class="container natfin">
        @if (!@$user->address->membership_certificate)
            <div class="natcert-alert">
                <i class="las la-certificate"></i>
                <div>
                    <strong>@lang('Membership certificate required')</strong>
                    <span>@lang('To remain part of the NATCODEV Coconut Farmers Cooperative, upload your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative.')</span>
                </div>
                <a href="{{ route('user.profile.setting') }}">@lang('Upload certificate')</a>
            </div>
        @endif

        @if ($user->kv != Status::KYC_VERIFIED)
BLADE;

if (strpos($view, 'natcert-alert') === false) {
    if (strpos($view, $needle) === false) {
        throw new RuntimeException('Dashboard insertion point not found.');
    }
    $view = str_replace($needle, $insert, $view);
    file_put_contents($viewPath, $view);
}

$css = <<<'CSS'

/* NATCODEV certificate dashboard alert */
.natcert-alert {
    align-items: center;
    background: linear-gradient(135deg, #fff8e4, #f5fbf7);
    border: 1px solid rgba(201, 154, 46, 0.38);
    border-radius: 8px;
    box-shadow: 0 14px 30px rgba(8, 44, 32, 0.08);
    display: flex;
    gap: 14px;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 14px 16px;
}
.natcert-alert i {
    color: #c99a2e;
    font-size: 28px;
}
.natcert-alert strong,
.natcert-alert span {
    display: block;
}
.natcert-alert strong {
    color: #082c20;
    font-weight: 900;
}
.natcert-alert span {
    color: #5b6b62;
    font-weight: 700;
}
.natcert-alert a {
    background: linear-gradient(135deg, #fff4cf, #c99a2e);
    border-radius: 8px;
    color: #10251b;
    flex: 0 0 auto;
    font-weight: 900;
    padding: 10px 14px;
}
@media (max-width: 767px) {
    .natcert-alert {
        align-items: flex-start;
        flex-direction: column;
    }
    .natcert-alert a {
        text-align: center;
        width: 100%;
    }
}
CSS;

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV certificate dashboard alert */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Certificate dashboard alert added.\n";
