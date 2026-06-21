<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

function patch_file($path, callable $patcher)
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read $path\n");
        exit(1);
    }
    $new = $patcher($contents);
    if ($new === null) {
        fwrite(STDERR, "Patch failed for $path\n");
        exit(1);
    }
    file_put_contents($path, $new);
}

$profileController = $root . '\\app\\Http\\Controllers\\User\\ProfileController.php';
patch_file($profileController, function ($contents) {
    if (strpos($contents, 'public function viewCertificate()') === false) {
        $method = <<<'PHP'

    public function viewCertificate()
    {
        $user = auth()->user();
        $address = (array) $user->address;
        $certificate = $address['membership_certificate_path'] ?? null;

        if (!$certificate || !is_file(base_path($certificate))) {
            abort(404);
        }

        return response()->file(base_path($certificate));
    }
PHP;
        $contents = str_replace("\n    public function changePassword()", $method . "\n\n    public function changePassword()", $contents);
    }

    $contents = str_replace(
        "\$address['membership_certificate_status'] = 'uploaded';",
        "\$address['membership_certificate_status'] = 'pending';\n                unset(\$address['membership_certificate_rejection_reason'], \$address['membership_certificate_reviewed_at'], \$address['membership_certificate_reviewed_by']);",
        $contents
    );

    return $contents;
});

$manageController = $root . '\\app\\Http\\Controllers\\Admin\\ManageUsersController.php';
patch_file($manageController, function ($contents) {
    if (strpos($contents, 'public function viewCertificate($id)') === false) {
        $method = <<<'PHP'

    public function viewCertificate($id)
    {
        $user = User::findOrFail($id);
        $address = (array) $user->address;
        $certificate = $address['membership_certificate_path'] ?? null;

        if (!$certificate || !is_file(base_path($certificate))) {
            abort(404);
        }

        return response()->file(base_path($certificate));
    }

    public function approveCertificate($id)
    {
        $user = User::findOrFail($id);
        $address = (array) $user->address;

        if (empty($address['membership_certificate_path']) || !is_file(base_path($address['membership_certificate_path']))) {
            $notify[] = ['error', 'No NATCODEV certificate found for this member'];
            return back()->withNotify($notify);
        }

        $address['membership_certificate_status'] = 'approved';
        $address['membership_certificate_reviewed_at'] = now()->toDateTimeString();
        $address['membership_certificate_reviewed_by'] = auth('admin')->id();
        unset($address['membership_certificate_rejection_reason']);

        $user->address = $address;
        $user->save();

        $notify[] = ['success', 'NATCODEV certificate approved successfully'];
        return back()->withNotify($notify);
    }

    public function rejectCertificate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $address = (array) $user->address;

        if (empty($address['membership_certificate_path']) || !is_file(base_path($address['membership_certificate_path']))) {
            $notify[] = ['error', 'No NATCODEV certificate found for this member'];
            return back()->withNotify($notify);
        }

        $address['membership_certificate_status'] = 'rejected';
        $address['membership_certificate_rejection_reason'] = $request->reason;
        $address['membership_certificate_reviewed_at'] = now()->toDateTimeString();
        $address['membership_certificate_reviewed_by'] = auth('admin')->id();

        $user->address = $address;
        $user->save();

        $notify[] = ['success', 'NATCODEV certificate rejected successfully'];
        return back()->withNotify($notify);
    }
PHP;
        $contents = str_replace("\n    public function update(Request \$request, \$id)", $method . "\n\n    public function update(Request \$request, \$id)", $contents);
    }

    $old = <<<'PHP'
        $user->address = [
            'address' => $request->address,
            'city'    => $request->city,
            'state'   => $request->state,
            'zip'     => $request->zip,
            'country' => @$country,
        ];
PHP;

    $new = <<<'PHP'
        $address = (array) $user->address;
        $address['address'] = $request->address;
        $address['city']    = $request->city;
        $address['state']   = $request->state;
        $address['zip']     = $request->zip;
        $address['country'] = @$country;
        $user->address = $address;
PHP;

    $contents = str_replace($old, $new, $contents);

    return $contents;
});

$adminRoutes = $root . '\\routes\\admin.php';
patch_file($adminRoutes, function ($contents) {
    if (strpos($contents, "certificate-view/{id}") === false) {
        $needle = "        Route::post('status/{id}', 'status')->name('status');\n";
        $insert = $needle
            . "        Route::get('certificate-view/{id}', 'viewCertificate')->name('certificate.view');\n"
            . "        Route::post('certificate-approve/{id}', 'approveCertificate')->name('certificate.approve');\n"
            . "        Route::post('certificate-reject/{id}', 'rejectCertificate')->name('certificate.reject');\n";
        $contents = str_replace($needle, $insert, $contents);
    }
    return $contents;
});

$userRoutes = $root . '\\routes\\user.php';
patch_file($userRoutes, function ($contents) {
    if (strpos($contents, "certificate-view") === false) {
        $needle = "                Route::post('profile-setting', 'submitProfile');\n";
        $insert = $needle . "                Route::get('certificate-view', 'viewCertificate')->name('profile.certificate.view');\n";
        $contents = str_replace($needle, $insert, $contents);
    }
    return $contents;
});

echo "CERTIFICATE_REVIEW_WORKFLOW_PATCHED\n";
