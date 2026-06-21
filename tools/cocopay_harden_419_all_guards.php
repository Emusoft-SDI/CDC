<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$handlerPath = $root . '\\app\\Exceptions\\Handler.php';
$handler = file_get_contents($handlerPath);
if ($handler === false) {
    fwrite(STDERR, "Unable to read Handler.php\n");
    exit(1);
}

if (strpos($handler, "route('staff.login')") === false) {
    $handler = str_replace(
        "            if (\$request->is('user') || \$request->is('user/*')) {\n                return redirect()->route('user.login')->withNotify(\$notify);\n            }\n\n            return redirect()->route('home')->withNotify(\$notify);",
        "            if (\$request->is('user') || \$request->is('user/*')) {\n                return redirect()->route('user.login')->withNotify(\$notify);\n            }\n\n            if (\$request->is('staff') || \$request->is('staff/*')) {\n                return redirect()->route('staff.login')->withNotify(\$notify);\n            }\n\n            return redirect()->route('home')->withNotify(\$notify);",
        $handler
    );
}

file_put_contents($handlerPath, $handler);

$viewPath = $root . '\\resources\\views\\errors\\419.blade.php';
$view = <<<'BLADE'
<!-- meta tags and other links -->
@php
    $targetRoute = route('home');
    $targetLabel = 'Go to Home';

    if (request()->is('admin') || request()->is('admin/*')) {
        $targetRoute = route('admin.login');
        $targetLabel = 'Go to Admin Login';
    } elseif (request()->is('user') || request()->is('user/*')) {
        $targetRoute = route('user.login');
        $targetLabel = 'Go to Member Login';
    } elseif (request()->is('staff') || request()->is('staff/*')) {
        $targetRoute = route('staff.login');
        $targetLabel = 'Go to Branch Staff Login';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $general->siteName($pageTitle ?? '419 | Session has expired') }}</title>
  <link rel="shortcut icon" type="image/png" href="{{ siteLogo() }}">
  <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/errors/css/main.css') }}">
</head>
  <body>
  <div class="error">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-7 text-center">
          <img src="{{ asset('assets/errors/images/error-419.png') }}" alt="@lang('image')">
          <h2><b>@lang('419')</b> @lang('Your session has expired.')</h2>
          <p>@lang('Please login again, then retry the action. Your data is protected from stale form submissions.')</p>
          <a href="{{ $targetRoute }}" class="cmn-btn mt-4">@lang($targetLabel)</a>
        </div>
      </div>
    </div>
  </div>
  </body>
</html>
BLADE;

file_put_contents($viewPath, $view);

echo "HARDENED_419_ALL_GUARDS\n";
