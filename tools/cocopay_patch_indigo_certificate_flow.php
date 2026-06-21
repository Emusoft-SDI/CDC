<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$register = $root . '\\resources\\views\\templates\\indigo_fusion\\user\\auth\\register.blade.php';
$profile = $root . '\\resources\\views\\templates\\indigo_fusion\\user\\profile_setting.blade.php';

$registerContents = file_get_contents($register);
if ($registerContents === false) {
    fwrite(STDERR, "Unable to read indigo register view\n");
    exit(1);
}

$oldRegisterBlock = <<<'BLADE'
                        <div class="col-md-12">
                            <div class="natcert-upload">
                                <label class="form-label required">@lang('NATCODEV Growers Certificate')</label>
                                <input type="file" class="form--control" name="grower_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
                                <p>@lang('Upload your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative. Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</p>
                            </div>
                        </div>
BLADE;

$newRegisterBlock = <<<'BLADE'
                        <div class="col-md-12">
                            <div class="natcert-upload">
                                <label class="form-label">@lang('Compulsory document after registration')</label>
                                <p>@lang('Your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative is compulsory and non-negotiable for membership completion. It is collected after account creation from your member profile, so it will not block registration.')</p>
                            </div>
                        </div>
BLADE;

if (strpos($registerContents, $oldRegisterBlock) === false) {
    fwrite(STDERR, "Expected indigo register certificate block not found\n");
    exit(1);
}

$registerContents = str_replace($oldRegisterBlock, $newRegisterBlock, $registerContents);
file_put_contents($register, $registerContents);

$profileContents = file_get_contents($profile);
if ($profileContents === false) {
    fwrite(STDERR, "Unable to read indigo profile view\n");
    exit(1);
}

if (strpos($profileContents, '$certificateUploaded') === false) {
    $profileContents = str_replace(
        "@section('content')\n",
        "@section('content')\n    @php\n        \$address = (array) \$user->address;\n        \$certificateUploaded = !empty(\$address['membership_certificate_path']) || !empty(\$address['membership_certificate']);\n    @endphp\n",
        $profileContents
    );
}

$profileContents = str_replace(
    "                                        <label class=\"form-label\">@lang('NATCODEV Growers Certificate')</label>",
    "                                        <label class=\"form-label\">@lang('Compulsory NATCODEV Membership Document')</label>",
    $profileContents
);

$profileContents = str_replace(
    "                                        <input class=\"form--control\" name=\"grower_certificate\" type=\"file\" accept=\".pdf,.jpg,.jpeg,.png\">",
    "                                        <input class=\"form--control\" name=\"grower_certificate\" type=\"file\" accept=\".pdf,.jpg,.jpeg,.png\" @if(!\$certificateUploaded) required @endif>",
    $profileContents
);

$profileContents = str_replace(
    "                                        <p>@lang('Upload a replacement only if NATCODEV has issued a newer certificate. Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</p>",
    "                                        <p>@lang('Upload your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative. This is compulsory and non-negotiable for membership completion. Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</p>",
    $profileContents
);

file_put_contents($profile, $profileContents);

echo "PATCHED_INDIGO_CERTIFICATE_FLOW\n";
