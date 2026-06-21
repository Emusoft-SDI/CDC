<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::orderBy('id')->first();
$user = App\Models\User::orderBy('id')->first();

$checks = [
    ['GET', '/', null, 'home'],
    ['GET', '/faq', null, 'faq'],
    ['GET', '/services', null, 'services'],
    ['GET', '/contact', null, 'contact'],
    ['GET', '/admin', null, 'admin_login'],
    ['GET', '/admin/dashboard', 'admin', 'admin_dashboard'],
    ['GET', '/admin/request-report', 'admin', 'admin_request_report'],
    ['GET', '/admin/ticket', 'admin', 'admin_tickets'],
    ['GET', '/user/dashboard', 'user', 'user_dashboard'],
    ['GET', '/user/transactions', 'user', 'user_transactions'],
    ['GET', '/user/deposit/history', 'user', 'user_deposit_history'],
    ['GET', '/user/loan/plans', 'user', 'user_loan_plans'],
    ['GET', '/user/support/knowledge-base', 'user', 'user_knowledge_base'],
];

foreach ($checks as [$method, $uri, $guard, $label]) {
    auth('admin')->logout();
    auth()->logout();

    if ($guard === 'admin' && $admin) {
        auth('admin')->login($admin);
    }
    if ($guard === 'user' && $user) {
        auth()->login($user);
    }

    $request = Illuminate\Http\Request::create($uri, $method);
    try {
        $response = $kernel->handle($request);
        $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        $status = $response->getStatusCode();
        $broken = str_contains($content, 'ErrorException')
            || str_contains($content, 'RuntimeException')
            || str_contains($content, 'Fatal error')
            || str_contains($content, 'Whoops');

        echo "{$label}|{$status}|broken=" . ($broken ? 'yes' : 'no') . "|len=" . strlen($content) . "\n";
        $kernel->terminate($request, $response);
    } catch (Throwable $exception) {
        echo "{$label}|EXCEPTION|" . get_class($exception) . '|' . $exception->getMessage() . "\n";
    }
}

