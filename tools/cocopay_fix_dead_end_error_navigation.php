<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$banFiles = [
    $root . '\\resources\\views\\templates\\crystal_sky\\user\\auth\\authorization\\ban.blade.php',
    $root . '\\resources\\views\\templates\\indigo_fusion\\user\\auth\\authorization\\ban.blade.php',
];

foreach ($banFiles as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read $file\n");
        exit(1);
    }

    $contents = str_replace(
        '<a class="btn btn--xl btn--base" href="{{ route(\'home\') }}"> @lang(\'Go to Home\') </a>',
        '<div class="d-flex flex-wrap justify-content-center gap-2">
                        <a class="btn btn--xl btn--base" href="{{ route(\'user.login\') }}"> @lang(\'Member Login\') </a>
                        <a class="btn btn--xl btn--outline--base" href="{{ route(\'home\') }}"> @lang(\'Cooperative Home\') </a>
                    </div>',
        $contents
    );

    file_put_contents($file, $contents);
}

$notFoundPath = $root . '\\resources\\views\\errors\\404.blade.php';
$notFound = <<<'BLADE'
<!-- meta tags and other links -->
@php
    $targetRoute = route('home');
    $targetLabel = 'Cooperative Home';

    if (request()->is('admin') || request()->is('admin/*')) {
        $targetRoute = route('admin.dashboard');
        $targetLabel = 'Admin Dashboard';
    } elseif (request()->is('user') || request()->is('user/*')) {
        $targetRoute = route('user.home');
        $targetLabel = 'Member Dashboard';
    } elseif (request()->is('staff') || request()->is('staff/*')) {
        $targetRoute = route('staff.dashboard');
        $targetLabel = 'Branch Staff Dashboard';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $general->siteName($pageTitle ?? '404 | Page not found') }}</title>
  <link rel="shortcut icon" type="image/png" href="{{ siteLogo() }}">
  <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/errors/css/main.css') }}">
</head>
  <body>
  <div class="error">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-7 text-center">
          <img src="{{ asset('assets/errors/images/error-404.png') }}" alt="@lang('image')">
          <h2><b>@lang('404')</b> @lang('Page not found')</h2>
          <p>@lang('The page you are looking for does not exist or may have moved.')</p>
          <a href="{{ $targetRoute }}" class="cmn-btn mt-4">@lang($targetLabel)</a>
        </div>
      </div>
    </div>
  </div>
  </body>
</html>
BLADE;

file_put_contents($notFoundPath, $notFound);

echo "FIXED_DEAD_END_ERROR_NAVIGATION\n";
