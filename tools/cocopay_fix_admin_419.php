<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$handlerPath = $root . '\\app\\Exceptions\\Handler.php';

$contents = file_get_contents($handlerPath);
if ($contents === false) {
    fwrite(STDERR, "Unable to read Handler.php\n");
    exit(1);
}

if (strpos($contents, 'Illuminate\\Session\\TokenMismatchException') === false) {
    $contents = str_replace(
        "use Illuminate\\Foundation\\Exceptions\\Handler as ExceptionHandler;\nuse Throwable;",
        "use Illuminate\\Foundation\\Exceptions\\Handler as ExceptionHandler;\nuse Illuminate\\Session\\TokenMismatchException;\nuse Throwable;",
        $contents
    );
}

if (strpos($contents, 'function render($request, Throwable $exception)') === false) {
    $method = <<<'PHP'

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            $notify[] = ['error', 'Your session expired. Please login again and retry the action.'];

            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect()->route('admin.login')->withNotify($notify);
            }

            if ($request->is('user') || $request->is('user/*')) {
                return redirect()->route('user.login')->withNotify($notify);
            }

            return redirect()->route('home')->withNotify($notify);
        }

        return parent::render($request, $exception);
    }
PHP;

    $contents = str_replace(
        "\n    protected function unauthenticated($request, AuthenticationException $exception)",
        $method . "\n\n    protected function unauthenticated($request, AuthenticationException $exception)",
        $contents
    );
}

if (file_put_contents($handlerPath, $contents) === false) {
    fwrite(STDERR, "Unable to write Handler.php\n");
    exit(1);
}

echo "PATCHED_ADMIN_419_HANDLER\n";
