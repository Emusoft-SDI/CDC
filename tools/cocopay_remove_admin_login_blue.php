<?php

$cssPath = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\assets\\admin\\css\\natcodev-admin.css';
$css = file_get_contents($cssPath);

$append = <<<'CSS'

/* Remove default Bootstrap/browser blue from the admin login surface. */
.natcodev-admin-login a,
.natcodev-admin-login .forget-text {
    color: var(--natcodev-green) !important;
}

.natcodev-admin-login a:hover,
.natcodev-admin-login .forget-text:hover {
    color: var(--natcodev-forest) !important;
}

.natcodev-admin-login .form-control:focus,
.natcodev-admin-login .form-check-input:focus,
.natcodev-admin-login .btn:focus,
.natcodev-admin-login .btn:active,
.natcodev-admin-login .cmn-btn:focus,
.natcodev-admin-login .cmn-btn:active {
    border-color: var(--natcodev-gold) !important;
    box-shadow: 0 0 0 .22rem rgba(217, 164, 65, .22) !important;
    outline: 0 !important;
}

.natcodev-admin-login .form-check-input:checked {
    background-color: var(--natcodev-green) !important;
    border-color: var(--natcodev-green) !important;
}

.natcodev-admin-login .form-control::selection,
.natcodev-admin-login label::selection,
.natcodev-admin-login p::selection,
.natcodev-admin-login h1::selection,
.natcodev-admin-login h3::selection,
.natcodev-admin-login span::selection {
    background: rgba(217, 164, 65, .35);
    color: var(--natcodev-forest);
}

.natcodev-admin-login input:-webkit-autofill,
.natcodev-admin-login input:-webkit-autofill:hover,
.natcodev-admin-login input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--natcodev-ink);
    -webkit-box-shadow: 0 0 0 1000px #f8fbf8 inset !important;
    border-color: rgba(217, 164, 65, .46) !important;
}
CSS;

if (!str_contains($css, 'Remove default Bootstrap/browser blue from the admin login surface')) {
    file_put_contents($cssPath, rtrim($css) . PHP_EOL . $append . PHP_EOL);
    echo 'ADMIN_LOGIN_BLUE_OVERRIDES_ADDED' . PHP_EOL;
} else {
    echo 'ADMIN_LOGIN_BLUE_OVERRIDES_ALREADY_PRESENT' . PHP_EOL;
}
