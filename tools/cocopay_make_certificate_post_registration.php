<?php

$controller = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/User/Auth/RegisterController.php';
$contents = file_get_contents($controller);

$contents = str_replace(
    <<<'PHP'
            'grower_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
PHP,
    <<<'PHP'
            'grower_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
        try {
            $certificateFile = fileUploader($data['grower_certificate'], getFilePath('verify'));
        } catch (\Exception $exp) {
            throw new \Exception('Could not upload NATCODEV growers certificate.');
        }
PHP,
    <<<'PHP'
        $certificateFile = null;
        if (!empty($data['grower_certificate'])) {
            try {
                $certificateFile = fileUploader($data['grower_certificate'], getFilePath('verify'));
            } catch (\Exception $exp) {
                throw new \Exception('Could not upload NATCODEV growers certificate.');
            }
        }
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
            'membership_certificate_type' => 'NATCODEV Growers Certificate',
            'membership_certificate' => $certificateFile,
            'membership_certificate_path' => getFilePath('verify') . '/' . $certificateFile,
            'membership_certificate_uploaded_at' => now()->toDateTimeString(),
PHP,
    <<<'PHP'
            'membership_certificate_type' => 'NATCODEV Growers Certificate',
            'membership_certificate' => $certificateFile,
            'membership_certificate_path' => $certificateFile ? getFilePath('verify') . '/' . $certificateFile : null,
            'membership_certificate_uploaded_at' => $certificateFile ? now()->toDateTimeString() : null,
            'membership_certificate_status' => $certificateFile ? 'uploaded' : 'pending',
PHP,
    $contents
);

file_put_contents($controller, $contents);

$view = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/crystal_sky/user/auth/register.blade.php';
$blade = file_get_contents($view);

$blade = str_replace(
    "@lang('Upload a NATCODEV-issued growers or cooperative certificate to support eligibility review.')",
    "@lang('You can upload your NATCODEV-issued certificate after account creation from your profile.')",
    $blade
);

$blade = str_replace(
    <<<'BLADE'
                        <div class="col-12">
                            <div class="form-group nat-certificate-box">
                                <label for="grower_certificate" class="form--label">@lang('NATCODEV Growers Certificate')</label>
                                <input id="grower_certificate" name="grower_certificate" type="file" class="form--control" accept=".pdf,.jpg,.jpeg,.png" required>
                                <strong>@lang('Required for cooperative membership review')</strong>
                                <span class="nat-field-note">@lang('Upload your NATCODEV growers certificate or a certificate issued by NATCODEV to your cooperative. Accepted: PDF, JPG, PNG. Max 5MB.')</span>
                            </div>
                        </div>
BLADE,
    <<<'BLADE'
                        <div class="col-12">
                            <div class="form-group nat-certificate-box">
                                <label for="grower_certificate" class="form--label">@lang('NATCODEV Growers Certificate')</label>
                                <input id="grower_certificate" name="grower_certificate" type="file" class="form--control" accept=".pdf,.jpg,.jpeg,.png">
                                <strong>@lang('Optional during registration')</strong>
                                <span class="nat-field-note">@lang('You may create your account now and upload your NATCODEV growers certificate later from your profile. Accepted: PDF, JPG, PNG. Max 5MB.')</span>
                            </div>
                        </div>
BLADE,
    $blade
);

$blade = str_replace(
    "@lang('Register as a NATCODEV Coconut Farmers Cooperative member. Nigeria is the default country, and your state/LGA and growers certificate are required.')",
    "@lang('Register as a NATCODEV Coconut Farmers Cooperative member. Nigeria is the default country. You can upload your growers certificate after registration.')",
    $blade
);

file_put_contents($view, $blade);

echo "certificate moved to post-registration flow\n";
