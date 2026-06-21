<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$registerView = $root . '\\resources\\views\\templates\\crystal_sky\\user\\auth\\register.blade.php';
$profileView = $root . '\\resources\\views\\templates\\crystal_sky\\user\\profile_setting.blade.php';
$registerController = $root . '\\app\\Http\\Controllers\\User\\Auth\\RegisterController.php';
$profileController = $root . '\\app\\Http\\Controllers\\User\\ProfileController.php';

$register = file_get_contents($registerView);
if ($register === false) {
    fwrite(STDERR, "Unable to read register view\n");
    exit(1);
}

$register = str_replace(
    "@lang('You can upload your NATCODEV-issued certificate after account creation from your profile.')",
    "@lang('This document is compulsory after registration and must be uploaded from your profile for membership completion.')",
    $register
);

$register = str_replace(
    "@lang('Register as a NATCODEV Coconut Farmers Cooperative member. Nigeria is the default country. You can upload your growers certificate after registration.')",
    "@lang('Register as a NATCODEV Coconut Farmers Cooperative member. Nigeria is the default country. Your NATCODEV growers certificate is compulsory after registration and must be uploaded from your profile.')",
    $register
);

$certificateBlock = <<<'BLADE'

                        <div class="col-12">
                            <div class="form-group nat-certificate-box">
                                <label for="grower_certificate" class="form--label">@lang('NATCODEV Growers Certificate')</label>
                                <input id="grower_certificate" name="grower_certificate" type="file" class="form--control" accept=".pdf,.jpg,.jpeg,.png">
                                <strong>@lang('Optional during registration')</strong>
                                <span class="nat-field-note">@lang('You may create your account now and upload your NATCODEV growers certificate later from your profile. Accepted: PDF, JPG, PNG. Max 5MB.')</span>
                            </div>
                        </div>
BLADE;

$replacementBlock = <<<'BLADE'

                        <div class="col-12">
                            <div class="form-group nat-certificate-box">
                                <strong>@lang('Compulsory document after registration')</strong>
                                <span class="nat-field-note">@lang('Your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative is non-negotiable for membership completion. It is collected after account creation from your member profile, so it will not block this registration form.')</span>
                            </div>
                        </div>
BLADE;

if (strpos($register, $certificateBlock) === false) {
    fwrite(STDERR, "Certificate block not found in register view\n");
    exit(1);
}

$register = str_replace($certificateBlock, $replacementBlock, $register);
file_put_contents($registerView, $register);

$regController = file_get_contents($registerController);
if ($regController === false) {
    fwrite(STDERR, "Unable to read RegisterController\n");
    exit(1);
}

$regController = str_replace(
    "            'grower_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',\n",
    "",
    $regController
);

$regController = preg_replace(
    "/\n        \\$certificateFile = null;\n        if \\(!empty\\(\\$data\\['grower_certificate'\\]\\)\\) \\{\n            try \\{\n                \\$certificateFile = fileUploader\\(\\$data\\['grower_certificate'\\], getFilePath\\('verify'\\)\\);\n            \\} catch \\(\\\\Exception \\$exp\\) \\{\n                throw new \\\\Exception\\('Could not upload NATCODEV growers certificate\\.'\\);\n            \\}\n        \\}\n/s",
    "\n        \$certificateFile = null;\n",
    $regController
);

$regController = str_replace(
    "            'membership_certificate' => \$certificateFile,\n            'membership_certificate_path' => \$certificateFile ? getFilePath('verify') . '/' . \$certificateFile : null,\n            'membership_certificate_uploaded_at' => \$certificateFile ? now()->toDateTimeString() : null,\n            'membership_certificate_status' => \$certificateFile ? 'uploaded' : 'pending',",
    "            'membership_certificate' => null,\n            'membership_certificate_path' => null,\n            'membership_certificate_uploaded_at' => null,\n            'membership_certificate_status' => 'pending',",
    $regController
);

file_put_contents($registerController, $regController);

$profile = file_get_contents($profileView);
if ($profile === false) {
    fwrite(STDERR, "Unable to read profile view\n");
    exit(1);
}

if (strpos($profile, '$address = (array) $user->address;') === false) {
    $profile = str_replace(
        "@section('content')\n",
        "@section('content')\n    @php\n        \$address = (array) \$user->address;\n        \$certificateUploaded = !empty(\$address['membership_certificate_path']);\n    @endphp\n",
        $profile
    );
}

$profileCss = <<<'BLADE'
    <style>
        .nat-required-document {
            border: 1px solid rgba(216, 168, 70, .42);
            background: #fff9ed;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }
        .nat-required-document.is-complete {
            border-color: rgba(15, 111, 69, .22);
            background: #eef8f0;
        }
        .nat-required-document__head {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .nat-required-document__icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0f6f45;
            color: #fff;
            font-size: 22px;
            flex: 0 0 auto;
        }
        .nat-required-document h5 {
            margin: 0 0 4px;
            color: #062c1f;
            font-weight: 900;
        }
        .nat-required-document p,
        .nat-required-document span {
            color: #657268;
            line-height: 1.6;
            margin: 0;
            font-size: 13px;
        }
        .nat-document-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 10px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            background: rgba(216, 168, 70, .16);
            color: #79571a;
        }
        .nat-document-status.is-complete {
            background: rgba(15, 111, 69, .12);
            color: #0f6f45;
        }
    </style>
BLADE;

if (strpos($profile, 'nat-required-document') === false) {
    $profile = str_replace(
        "@section('content')\n",
        "@section('content')\n" . $profileCss . "\n",
        $profile
    );
}

$profileField = <<<'BLADE'
                    <div class="col-12">
                        <div class="nat-required-document @if($certificateUploaded) is-complete @endif">
                            <div class="nat-required-document__head">
                                <span class="nat-required-document__icon"><i class="las la-certificate"></i></span>
                                <div>
                                    <h5>@lang('Compulsory NATCODEV Membership Document')</h5>
                                    <p>@lang('Upload your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative. This is a compulsory, non-negotiable document for membership completion, but it is handled after registration.')</p>
                                    <span class="nat-document-status @if($certificateUploaded) is-complete @endif">
                                        @if($certificateUploaded)
                                            @lang('Certificate uploaded')
                                        @else
                                            @lang('Certificate required')
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <label class="form-label" for="growerCertificate">@lang('NATCODEV Growers Certificate')</label>
                            <input type="file" class="form--control" id="growerCertificate" name="grower_certificate" accept=".pdf,.jpg,.jpeg,.png" @if(!$certificateUploaded) required @endif>
                            <span>@lang('Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</span>
                        </div>
                    </div>
BLADE;

if (strpos($profile, 'id="growerCertificate"') === false) {
    $profile = str_replace(
        "                    <div class=\"col-12\">\n                        <div class=\"form-group\">\n                            <input type=\"file\" class=\"form--control\" id=\"imageUpload\" name=\"image\" type='file' accept=\".png, .jpg, .jpeg\">\n                        </div>\n                    </div>",
        "                    <div class=\"col-12\">\n                        <div class=\"form-group\">\n                            <label class=\"form-label\" for=\"imageUpload\">@lang('Profile Photo')</label>\n                            <input type=\"file\" class=\"form--control\" id=\"imageUpload\" name=\"image\" type='file' accept=\".png, .jpg, .jpeg\">\n                        </div>\n                    </div>\n" . $profileField,
        $profile
    );
}

file_put_contents($profileView, $profile);

$profController = file_get_contents($profileController);
if ($profController === false) {
    fwrite(STDERR, "Unable to read ProfileController\n");
    exit(1);
}

$profController = str_replace(
    "        \$request->validate([\n            'firstname'   => 'required|string',",
    "        \$user = auth()->user();\n        \$address = (array) \$user->address;\n        \$certificateRequired = empty(\$address['membership_certificate_path']) ? 'required' : 'nullable';\n\n        \$request->validate([\n            'firstname'   => 'required|string',",
    $profController
);

$profController = str_replace(
    "            'grower_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'",
    "            'grower_certificate' => \$certificateRequired . '|file|mimes:pdf,jpg,jpeg,png|max:5120'",
    $profController
);

$profController = str_replace(
    "\n        \$user = auth()->user();\n\n        if (\$request->hasFile('image')) {",
    "\n        if (\$request->hasFile('image')) {",
    $profController
);

$profController = str_replace(
    "\n        \$address = (array) \$user->address;\n\n        if (\$request->hasFile('grower_certificate')) {",
    "\n        if (\$request->hasFile('grower_certificate')) {",
    $profController
);

$profController = str_replace(
    "                \$address['membership_certificate_uploaded_at'] = now()->toDateTimeString();",
    "                \$address['membership_certificate_uploaded_at'] = now()->toDateTimeString();\n                \$address['membership_certificate_status'] = 'uploaded';",
    $profController
);

file_put_contents($profileController, $profController);

echo "MOVED_CERTIFICATE_TO_PROFILE\n";
