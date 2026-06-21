<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$headerPath = $root . '/core/resources/views/templates/indigo_fusion/partials/header.blade.php';
$cssPath = $root . '/assets/templates/indigo_fusion/css/custom.css';

if (!is_file($headerPath)) {
    throw new RuntimeException("Missing header view: {$headerPath}");
}
if (!is_file($cssPath)) {
    throw new RuntimeException("Missing CSS: {$cssPath}");
}

$header = file_get_contents($headerPath);
$old = <<<'BLADE'
                            @guest
                                <a class="btn btn-sm header-base-button me-3 py-2" href="{{ route('user.login') }}">@lang('Sign In')</a>
                                <a class="btn btn-sm btn--base py-2 text-white" href="{{ route('user.register') }}">@lang('Sign Up')</a>
                            @else
                                <a class="btn btn-sm btn--base py-2 text-white logout-btn" href="{{ route('user.logout') }}">@lang('Logout')</a>
                            @endguest
BLADE;

$new = <<<'BLADE'
                            @guest
                                <a class="btn btn-sm header-base-button me-3 py-2" href="{{ route('user.login') }}">@lang('Sign In')</a>
                                <a class="btn btn-sm btn--base py-2 text-white" href="{{ route('user.register') }}">@lang('Sign Up')</a>
                            @else
                                @php
                                    $publicUser = auth()->user();
                                    $publicName = trim(($publicUser->firstname ?? '') . ' ' . ($publicUser->lastname ?? ''));
                                    $publicName = $publicName ?: ($publicUser->username ?? __('Member'));
                                    $publicInitial = strtoupper(substr($publicName, 0, 1));
                                @endphp
                                <div class="nat-public-member-actions">
                                    <a class="nat-public-dashboard-btn" href="{{ route('user.home') }}">
                                        <i class="las la-th-large"></i><span>@lang('Dashboard')</span>
                                    </a>
                                    <div class="dropdown">
                                        <a class="nat-public-member-chip dropdown-toggle" href="#" id="publicMemberMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span>{{ $publicInitial }}</span><small>{{ __($publicName) }}</small>
                                        </a>
                                        <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="publicMemberMenu">
                                            <li><a class="dropdown-item" href="{{ route('user.home') }}"><i class="las la-th-large"></i>@lang('Dashboard')</a></li>
                                            <li><a class="dropdown-item" href="{{ route('user.transaction.history') }}"><i class="las la-receipt"></i>@lang('Transactions')</a></li>
                                            <li><a class="dropdown-item" href="{{ route('user.profile.setting') }}"><i class="las la-user-circle"></i>@lang('Profile')</a></li>
                                            <li><a class="dropdown-item" href="{{ route('user.logout') }}"><i class="las la-sign-out-alt"></i>@lang('Logout')</a></li>
                                        </ul>
                                    </div>
                                </div>
                            @endguest
BLADE;

if (strpos($header, $old) === false) {
    throw new RuntimeException('Expected public auth action block was not found.');
}

$header = str_replace($old, $new, $header);
file_put_contents($headerPath, $header);

$css = <<<'CSS'

/* NATCODEV public logged-in return path */
.nat-public-member-actions {
    align-items: center;
    display: flex;
    gap: 10px;
}
.nat-public-dashboard-btn,
.nat-public-member-chip {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-size: 13px;
    font-weight: 900;
    min-height: 42px;
}
.nat-public-dashboard-btn {
    background: linear-gradient(135deg, #fff4cf, #c99a2e);
    border: 1px solid rgba(201, 154, 46, 0.4);
    color: #10251b;
    gap: 7px;
    padding: 9px 14px;
}
.nat-public-dashboard-btn:hover {
    color: #10251b;
    filter: brightness(0.98);
}
.nat-public-member-chip {
    background: #f7fbf8;
    border: 1px solid #dfe9e3;
    color: #17392a;
    gap: 8px;
    padding: 6px 10px 6px 7px;
}
.nat-public-member-chip span {
    align-items: center;
    background: linear-gradient(135deg, #087a45, #082c20);
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    height: 30px;
    justify-content: center;
    width: 30px;
}
.nat-public-member-chip small {
    color: #17392a;
    max-width: 96px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@media (max-width: 991px) {
    .nat-public-member-actions {
        align-items: stretch;
        flex-direction: column;
        margin-top: 12px;
        width: 100%;
    }
    .nat-public-dashboard-btn,
    .nat-public-member-chip {
        justify-content: center;
        width: 100%;
    }
}
CSS;

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV public logged-in return path */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Public logged-in dashboard return path applied.\n";
