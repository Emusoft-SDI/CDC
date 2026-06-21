<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Http\\Controllers\\Admin\\FrontendController.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read FrontendController.php\n");
    exit(1);
}

$contents = str_replace(
    "        \$purifier  = new \\HTMLPurifier();\n        \$valInputs = \$request->except('_token', 'image_input', 'key', 'status', 'type', 'id');",
    "        \$purifier          = new \\HTMLPurifier();\n        \$inputContentValue = [];\n        \$valInputs         = \$request->except('_token', 'image_input', 'key', 'status', 'type', 'id');",
    $contents
);

$contents = str_replace(
    "        \$temPaths = array_filter(glob('core/resources/views/templates/*'), 'is_dir');\n        foreach (\$temPaths as \$temp) {\n            \$arr         = explode('/', \$temp);\n            \$tempname    = end(\$arr);\n            \$templates[] = \"templates.\$tempname.\";\n        }\n\n        \$request->validate([\n            'tempname' => 'required|in:' . implode(',', \$templates),\n        ]);",
    "        \$templates = [];\n        \$temPaths  = array_filter(glob(resource_path('views/templates/*')) ?: [], 'is_dir');\n        foreach (\$temPaths as \$temp) {\n            \$tempname    = basename(\$temp);\n            \$templates[] = \"templates.\$tempname.\";\n        }\n\n        \$request->validate([\n            'tempname' => 'required|in:' . implode(',', \$templates),\n        ]);",
    $contents
);

if (file_put_contents($path, $contents) === false) {
    fwrite(STDERR, "Unable to write FrontendController.php\n");
    exit(1);
}

echo "PATCHED_FRONTEND_CONTROLLER_FRAGILE_ARRAYS\n";
