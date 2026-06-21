<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';

$sourceKb = $root . '/resources/views/templates/indigo_fusion/user/support/knowledge_base.blade.php';
$targetKbDir = $root . '/resources/views/templates/crystal_sky/user/support';
$targetKb = $targetKbDir . '/knowledge_base.blade.php';
if (!is_dir($targetKbDir)) {
    mkdir($targetKbDir, 0777, true);
}
copy($sourceKb, $targetKb);

chdir($root);
require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$template = activeTemplate();
$page = App\Models\Page::where('tempname', $template)->where('slug', 'services')->first() ?? new App\Models\Page();
$page->tempname = $template;
$page->name = 'Services';
$page->slug = 'services';
$page->secs = json_encode(['service', 'why_choose', 'feature', 'faq', 'subscribe']);
$page->save();

echo "active_template_pages_fixed={$template}\n";
