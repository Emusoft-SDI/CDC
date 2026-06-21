<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Http\\Controllers\\Admin\\FrontendController.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read FrontendController.php\n");
    exit(1);
}

$old = <<<'PHP'
    public function templates() {
        $pageTitle = 'Templates';
        $temPaths  = array_filter(glob('core/resources/views/templates/*'), 'is_dir');
        foreach ($temPaths as $key => $temp) {
            $arr                      = explode('/', $temp);
            $tempname                 = end($arr);
            $templates[$key]['name']  = $tempname;
            $templates[$key]['image'] = asset($temp) . '/preview.jpg';
        }
        $extraTemplates = json_decode(getTemplates(), true);
        return view('admin.frontend.templates', compact('pageTitle', 'templates', 'extraTemplates'));
    }
PHP;

$new = <<<'PHP'
    public function templates() {
        $pageTitle = 'Templates';
        $templates = [];
        $temPaths  = array_filter(glob(resource_path('views/templates/*')) ?: [], 'is_dir');

        foreach ($temPaths as $key => $temp) {
            $tempname                 = basename($temp);
            $templates[$key]['name']  = $tempname;
            $templates[$key]['image'] = asset('core/resources/views/templates/' . $tempname . '/preview.jpg');
        }

        $extraTemplates = json_decode(getTemplates(), true) ?? [];
        return view('admin.frontend.templates', compact('pageTitle', 'templates', 'extraTemplates'));
    }
PHP;

if (strpos($contents, $old) === false) {
    fwrite(STDERR, "Expected templates() method not found\n");
    exit(1);
}

$contents = str_replace($old, $new, $contents);

if (file_put_contents($path, $contents) === false) {
    fwrite(STDERR, "Unable to write FrontendController.php\n");
    exit(1);
}

echo "PATCHED_FRONTEND_TEMPLATES_CONTROLLER\n";
