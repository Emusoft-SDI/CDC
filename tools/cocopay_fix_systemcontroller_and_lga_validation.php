<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';

$systemFile = $base . '/app/Http/Controllers/Admin/SystemController.php';
$system = file_get_contents($systemFile);

$needle = <<<'PHP'
    public function systemUpdate() {
        $pageTitle = 'System Updates';
        $updates = UpdateLog::orderBy('id','desc')->paginate(getPaginate());
        return view('admin.system.update',compact('pageTitle','updates'));
    }
   
        if(!extension_loaded('zip')){
PHP;

$replacement = <<<'PHP'
    public function systemUpdate() {
        $pageTitle = 'System Updates';
        $updates = UpdateLog::orderBy('id','desc')->paginate(getPaginate());
        return view('admin.system.update',compact('pageTitle','updates'));
    }

    public function updateUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', new FileTypeValidate(['zip'])],
        ]);

        if(!extension_loaded('zip')){
PHP;

if (strpos($system, 'public function updateUpload(Request $request)') === false) {
    if (strpos($system, $needle) === false) {
        throw new RuntimeException('Could not find malformed SystemController upload block.');
    }
    $system = str_replace($needle, $replacement, $system);
    file_put_contents($systemFile, $system);
}

$registerFile = $base . '/app/Http/Controllers/User/Auth/RegisterController.php';
$register = file_get_contents($registerFile);

if (strpos($register, 'use Illuminate\\Validation\\Rule;') === false) {
    $register = str_replace(
        "use Illuminate\\Support\\Facades\\Validator;\n",
        "use Illuminate\\Support\\Facades\\Validator;\nuse Illuminate\\Validation\\Rule;\n",
        $register
    );
}

$old = <<<'PHP'
            'state_id'     => 'required|exists:nigeria_states,id',
            'lga_id'       => 'required|exists:nigeria_lgas,id',
PHP;

$new = <<<'PHP'
            'state_id'     => 'required|exists:nigeria_states,id',
            'lga_id'       => [
                'required',
                Rule::exists('nigeria_lgas', 'id')->where(function ($query) use ($data) {
                    return $query->where('state_id', $data['state_id'] ?? 0);
                }),
            ],
PHP;

if (strpos($register, $old) !== false) {
    $register = str_replace($old, $new, $register);
    file_put_contents($registerFile, $register);
}

echo "Fixed Admin SystemController and scoped LGA validation." . PHP_EOL;

