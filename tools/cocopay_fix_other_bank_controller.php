<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/OtherBankController.php';
$contents = file_get_contents($file);

$contents = str_replace(
    <<<'PHP'
        $form      = $bank->form;
        $form->mergeDefaultTransferFields();
PHP,
    <<<'PHP'
        $form      = $bank->form ?? new Form();
        $form->mergeDefaultTransferFields();
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
            $bank         = OtherBank::findOrFail($id);
            $form         = $formProcessor->generate('other_bank', true, 'id', $bank->form_id);
            $message      = "New bank updated successfully";
PHP,
    <<<'PHP'
            $bank = OtherBank::findOrFail($id);

            if ($bank->form_id) {
                $form = $formProcessor->generate('other_bank', true, 'id', $bank->form_id);
            } else {
                $form = $formProcessor->generate('other_bank');
            }

            $message = "Bank updated successfully";
PHP,
    $contents
);

file_put_contents($file, $contents);
echo "OtherBankController patched\n";
