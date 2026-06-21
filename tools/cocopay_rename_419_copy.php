<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\errors\\419.blade.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read 419.blade.php\n");
    exit(1);
}

$contents = str_replace(
    "{{ \$general->siteName(\$pageTitle ?? '419 | Session has expired') }}",
    "{{ \$general->siteName(\$pageTitle ?? 'Session Expired | NATCODEV Cooperative') }}",
    $contents
);

$contents = str_replace(
    "<h2><b>@lang('419')</b> @lang('Your session has expired.')</h2>",
    "<h2>@lang('Session Expired')</h2>",
    $contents
);

$contents = str_replace(
    "<p>@lang('Please login again, then retry the action. Your data is protected from stale form submissions.')</p>",
    "<p>@lang('Please login again, then retry the action. This protects your NATCODEV Cooperative account from stale form submissions.')</p>",
    $contents
);

file_put_contents($path, $contents);

echo "RENAMED_419_VISIBLE_COPY\n";
