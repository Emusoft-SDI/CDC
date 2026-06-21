<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Exceptions\\Handler.php';
$contents = file_get_contents($path);
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

if (strpos($contents, 'public function render($request, Throwable $exception)') === false) {
    $renderMethod = <<<'PHP'

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

    $needle = "\n" . '    protected function unauthenticated($request, AuthenticationException $exception)';
    if (strpos($contents, $needle) === false) {
        fwrite(STDERR, "Insertion point not found\n");
        exit(1);
    }

    $contents = str_replace($needle, $renderMethod . "\n" . $needle, $contents);
}

if (file_put_contents($path, $contents) === false) {
    fwrite(STDERR, "Unable to write Handler.php\n");
    exit(1);
}

echo "INSERTED_ADMIN_419_RENDER\n";
