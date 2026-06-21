<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$registerController = $root . '/app/Http/Controllers/User/Auth/RegisterController.php';
$profileController = $root . '/app/Http/Controllers/User/ProfileController.php';
$registerView = $root . '/resources/views/templates/indigo_fusion/user/auth/register.blade.php';
$profileView = $root . '/resources/views/templates/indigo_fusion/user/profile_setting.blade.php';
$cssPath = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/templates/indigo_fusion/css/custom.css';

foreach ([$registerController, $profileController, $registerView, $profileView, $cssPath] as $path) {
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }
}

$controller = file_get_contents($registerController);
$controller = str_replace(
    "            'lga_id'       => [\n                'required',\n                Rule::exists('nigeria_lgas', 'id')->where(function (\$query) use (\$data) {\n                    return \$query->where('state_id', \$data['state_id'] ?? 0);\n                }),\n            ],\n            'agree'        => \$agree,",
    "            'lga_id'       => [\n                'required',\n                Rule::exists('nigeria_lgas', 'id')->where(function (\$query) use (\$data) {\n                    return \$query->where('state_id', \$data['state_id'] ?? 0);\n                }),\n            ],\n            'grower_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',\n            'agree'        => \$agree,",
    $controller
);
$controller = str_replace(
    "        \$user->address = [\n            'address' => '',\n            'state'   => DB::table('nigeria_states')->where('id', \$data['state_id'])->value('state_name') ?: '',\n            'state_id' => \$data['state_id'] ?? null,\n            'lga'     => DB::table('nigeria_lgas')->where('id', \$data['lga_id'])->value('lga_name') ?: '',\n            'lga_id'  => \$data['lga_id'] ?? null,\n            'zip'     => '',\n            'country' => 'Nigeria',\n            'city'    => '',\n        ];",
    "        try {\n            \$certificateFile = fileUploader(\$data['grower_certificate'], getFilePath('verify'));\n        } catch (\\Exception \$exp) {\n            throw new \\Exception('Could not upload NATCODEV growers certificate.');\n        }\n\n        \$user->address = [\n            'address' => '',\n            'state'   => DB::table('nigeria_states')->where('id', \$data['state_id'])->value('state_name') ?: '',\n            'state_id' => \$data['state_id'] ?? null,\n            'lga'     => DB::table('nigeria_lgas')->where('id', \$data['lga_id'])->value('lga_name') ?: '',\n            'lga_id'  => \$data['lga_id'] ?? null,\n            'zip'     => '',\n            'country' => 'Nigeria',\n            'city'    => '',\n            'membership_certificate_type' => 'NATCODEV Growers Certificate',\n            'membership_certificate' => \$certificateFile,\n            'membership_certificate_path' => getFilePath('verify') . '/' . \$certificateFile,\n            'membership_certificate_uploaded_at' => now()->toDateTimeString(),\n        ];",
    $controller
);
file_put_contents($registerController, $controller);

$register = file_get_contents($registerView);
$register = str_replace(
    '<form action="{{ route(\'user.register\') }}" method="POST" class="verify-gcaptcha account-form">',
    '<form action="{{ route(\'user.register\') }}" method="POST" enctype="multipart/form-data" class="verify-gcaptcha account-form">',
    $register
);
$registerNeedle = <<<'BLADE'
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required">@lang('Password')</label>
BLADE;
$registerInsert = <<<'BLADE'
                        <div class="col-md-12">
                            <div class="natcert-upload">
                                <label class="form-label required">@lang('NATCODEV Growers Certificate')</label>
                                <input type="file" class="form--control" name="grower_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
                                <p>@lang('Upload your NATCODEV Growers Certificate or any certificate issued by NATCODEV to your cooperative. Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</p>
                            </div>
                        </div>

BLADE;
if (strpos($register, $registerInsert) === false) {
    $register = str_replace($registerNeedle, $registerInsert . $registerNeedle, $register);
}
file_put_contents($registerView, $register);

$profile = file_get_contents($profileController);
$profile = str_replace(
    "            'image'       => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])]\n        ], [",
    "            'image'       => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],\n            'grower_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'\n        ], [",
    $profile
);
$profile = str_replace(
    "        \$user->firstname = \$request->firstname;\n        \$user->lastname = \$request->lastname;\n\n        \$user->address = [\n            'address' => \$request->address,\n            'state' => \$request->state,\n            'zip' => \$request->zip,\n            'country' => @\$user->address->country,\n            'city' => \$request->city,\n        ];",
    "        \$address = (array) \$user->address;\n\n        if (\$request->hasFile('grower_certificate')) {\n            try {\n                \$oldCertificate = \$address['membership_certificate_path'] ?? null;\n                \$certificateFile = fileUploader(\$request->grower_certificate, getFilePath('verify'), null, null);\n                if (\$oldCertificate && file_exists(\$oldCertificate)) {\n                    fileManager()->removeFile(\$oldCertificate);\n                }\n                \$address['membership_certificate_type'] = 'NATCODEV Growers Certificate';\n                \$address['membership_certificate'] = \$certificateFile;\n                \$address['membership_certificate_path'] = getFilePath('verify') . '/' . \$certificateFile;\n                \$address['membership_certificate_uploaded_at'] = now()->toDateTimeString();\n            } catch (\\Exception \$exp) {\n                \$notify[] = ['error', 'Couldn\\'t upload your NATCODEV growers certificate'];\n                return back()->withNotify(\$notify);\n            }\n        }\n\n        \$user->firstname = \$request->firstname;\n        \$user->lastname = \$request->lastname;\n\n        \$address['address'] = \$request->address;\n        \$address['state'] = \$request->state;\n        \$address['zip'] = \$request->zip;\n        \$address['country'] = @\$user->address->country;\n        \$address['city'] = \$request->city;\n\n        \$user->address = \$address;",
    $profile
);
file_put_contents($profileController, $profile);

$profileViewContent = file_get_contents($profileView);
$profileViewContent = str_replace(
    "                            <li>\n                                <span class=\"caption\">@lang('Country')</span>\n                                <span class=\"value\">{{ \$user->address->country }}</span>\n                            </li>\n\n                        </ul>",
    "                            <li>\n                                <span class=\"caption\">@lang('Country')</span>\n                                <span class=\"value\">{{ \$user->address->country }}</span>\n                            </li>\n\n                            <li>\n                                <span class=\"caption\">@lang('Membership Certificate')</span>\n                                <span class=\"value\">{{ @\$user->address->membership_certificate ? __('Uploaded') : __('Required') }}</span>\n                            </li>\n\n                        </ul>",
    $profileViewContent
);
$profileNeedle = <<<'BLADE'
                                <div class="col">
                                    <div class="form-group">
                                        <label>@lang('Image')</label>
BLADE;
$profileInsert = <<<'BLADE'
                                <div class="col-lg-12">
                                    <div class="natcert-upload">
                                        <label class="form-label">@lang('NATCODEV Growers Certificate')</label>
                                        @if (@$user->address->membership_certificate)
                                            <div class="natcert-current">
                                                <i class="las la-certificate"></i>
                                                <span>@lang('Certificate on file')</span>
                                                <small>{{ showDateTime(@$user->address->membership_certificate_uploaded_at) }}</small>
                                            </div>
                                        @endif
                                        <input class="form--control" name="grower_certificate" type="file" accept=".pdf,.jpg,.jpeg,.png">
                                        <p>@lang('Upload a replacement only if NATCODEV has issued a newer certificate. Accepted formats: PDF, JPG, PNG. Maximum size: 5MB.')</p>
                                    </div>
                                </div>

BLADE;
if (strpos($profileViewContent, 'name="grower_certificate"') === false) {
    $profileViewContent = str_replace($profileNeedle, $profileInsert . $profileNeedle, $profileViewContent);
}
file_put_contents($profileView, $profileViewContent);

$css = <<<'CSS'

/* NATCODEV membership certificate upload */
.natcert-upload {
    background: #fff8e4;
    border: 1px solid rgba(201, 154, 46, 0.36);
    border-radius: 8px;
    margin-bottom: 18px;
    padding: 14px;
}
.natcert-upload label {
    color: #082c20;
    font-weight: 900;
}
.natcert-upload p {
    color: #5b6b62;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.5;
    margin: 8px 0 0;
}
.natcert-current {
    align-items: center;
    background: #e9f7ef;
    border: 1px solid #d7e9dc;
    border-radius: 8px;
    color: #087a45;
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    padding: 10px 12px;
}
.natcert-current i {
    color: #c99a2e;
    font-size: 22px;
}
.natcert-current span {
    font-weight: 900;
}
.natcert-current small {
    color: #617268;
    margin-left: auto;
}
CSS;

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV membership certificate upload */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Growers certificate requirement applied.\n";
