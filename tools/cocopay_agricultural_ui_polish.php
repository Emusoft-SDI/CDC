<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$cssFiles = [
    $base . '/assets/templates/indigo_fusion/css/custom.css',
    $base . '/assets/templates/crystal_sky/css/custom.css',
];

$css = <<<'CSS'

/* NATCODEV agricultural polish */
.site-logo img,
.footer-logo img,
.auth-form__logo img {
    height: auto;
    max-height: 76px;
    max-width: 330px;
    object-fit: contain;
    width: auto;
}
.header__bottom {
    background: rgba(255, 255, 255, .96);
    border-bottom: 1px solid rgba(22, 118, 64, .14);
    box-shadow: 0 12px 30px rgba(24, 52, 33, .08);
}
.header-base-button,
.btn--base {
    box-shadow: 0 8px 18px rgba(22, 118, 64, .18);
}
.btn--base:hover,
.natco-action:hover,
.natco-metric:hover {
    transform: translateY(-1px);
}
.natco-member-hero {
    border: 1px solid rgba(255, 255, 255, .18);
    box-shadow: 0 18px 50px rgba(21, 65, 36, .18);
    position: relative;
}
.natco-member-hero::after {
    background: linear-gradient(90deg, rgba(242, 181, 66, .95), rgba(148, 194, 70, .92), rgba(22, 118, 64, .85));
    bottom: 0;
    content: "";
    height: 6px;
    left: 0;
    position: absolute;
    right: 0;
}
.natco-balance-panel {
    backdrop-filter: blur(3px);
}
.natco-action,
.natco-metric {
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.natco-action:hover,
.natco-metric:hover {
    border-color: rgba(22, 118, 64, .35);
    box-shadow: 0 16px 34px rgba(31, 80, 45, .12);
}
.natco-action--strong {
    background: linear-gradient(135deg, #e9f8ed, #fff8e7);
}
.natco-flow-heading {
    background: linear-gradient(135deg, #f7fbf4, #fff8e7);
}
.natco-form-card,
.natco-loan-summary {
    border-top: 4px solid hsl(var(--base));
}
.natco-form-card .form--control:focus {
    border-color: hsl(var(--base));
    box-shadow: 0 0 0 4px rgba(22, 118, 64, .12);
}
.plan-card {
    border: 1px solid rgba(22, 118, 64, .16);
    box-shadow: 0 12px 32px rgba(30, 70, 40, .08);
    overflow: hidden;
}
.plan-card__header {
    background: linear-gradient(135deg, #167640, #94c246);
}
.plan-card__body {
    background: linear-gradient(180deg, #ffffff, #fbfff7);
}
.custom--card,
.card-widget,
.d-widget,
.dashboard-table {
    border-radius: 8px;
}
@media (max-width: 575px) {
    .site-logo img,
    .footer-logo img,
    .auth-form__logo img {
        max-height: 58px;
        max-width: 245px;
    }
}
CSS;

$marker = '/* NATCODEV agricultural polish */';
foreach ($cssFiles as $file) {
    if (!is_file($file)) {
        throw new RuntimeException("Missing CSS file: {$file}");
    }

    $existing = file_get_contents($file);
    if (strpos($existing, $marker) === false) {
        file_put_contents($file, rtrim($existing) . PHP_EOL . $css . PHP_EOL);
    }
}

echo "Applied NATCODEV agricultural UI polish." . PHP_EOL;

