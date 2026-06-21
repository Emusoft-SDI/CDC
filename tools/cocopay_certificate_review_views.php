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

$crystalProfile = $root . '\\resources\\views\\templates\\crystal_sky\\user\\profile_setting.blade.php';
patch_file($crystalProfile, function ($contents) {
    if (strpos($contents, '$certificateStatus') === false) {
        $contents = str_replace(
            "        \$certificateUploaded = !empty(\$address['membership_certificate_path']);\n",
            "        \$certificateUploaded = !empty(\$address['membership_certificate_path']);\n        \$certificateStatus = \$address['membership_certificate_status'] ?? (\$certificateUploaded ? 'pending' : 'missing');\n",
            $contents
        );
    }

    $contents = str_replace(
        "                                            @lang('Certificate uploaded')",
        "                                            {{ __(ucwords(str_replace('_', ' ', \$certificateStatus))) }}",
        $contents
    );

    if (strpos($contents, "route('user.profile.certificate.view')") === false) {
        $contents = str_replace(
            "                            <label class=\"form-label\" for=\"growerCertificate\">@lang('NATCODEV Growers Certificate')</label>",
            "                            @if(\$certificateUploaded)\n                                <a href=\"{{ route('user.profile.certificate.view') }}\" target=\"_blank\" class=\"btn btn-sm btn--base mb-2\"><i class=\"las la-eye\"></i> @lang('View Current Certificate')</a>\n                            @endif\n                            @if(\$certificateStatus === 'rejected' && !empty(\$address['membership_certificate_rejection_reason']))\n                                <p class=\"text--danger mb-2\">@lang('Rejected reason'): {{ \$address['membership_certificate_rejection_reason'] }}</p>\n                            @endif\n                            <label class=\"form-label\" for=\"growerCertificate\">@lang('NATCODEV Growers Certificate')</label>",
            $contents
        );
    }
    return $contents;
});

$indigoProfile = $root . '\\resources\\views\\templates\\indigo_fusion\\user\\profile_setting.blade.php';
patch_file($indigoProfile, function ($contents) {
    if (strpos($contents, '$certificateStatus') === false) {
        $contents = str_replace(
            "        \$certificateUploaded = !empty(\$address['membership_certificate_path']) || !empty(\$address['membership_certificate']);\n",
            "        \$certificateUploaded = !empty(\$address['membership_certificate_path']) || !empty(\$address['membership_certificate']);\n        \$certificateStatus = \$address['membership_certificate_status'] ?? (\$certificateUploaded ? 'pending' : 'missing');\n",
            $contents
        );
    }

    if (strpos($contents, "route('user.profile.certificate.view')") === false) {
        $contents = str_replace(
            "                                                <span>@lang('Certificate on file')</span>",
            "                                                <span>{{ __(ucwords(str_replace('_', ' ', \$certificateStatus))) }}</span>",
            $contents
        );

        $contents = str_replace(
            "                                        <input class=\"form--control\" name=\"grower_certificate\" type=\"file\" accept=\".pdf,.jpg,.jpeg,.png\" @if(!\$certificateUploaded) required @endif>",
            "                                        @if(\$certificateUploaded)\n                                            <a href=\"{{ route('user.profile.certificate.view') }}\" target=\"_blank\" class=\"btn btn-sm btn--base mb-2\"><i class=\"las la-eye\"></i> @lang('View Current Certificate')</a>\n                                        @endif\n                                        @if(\$certificateStatus === 'rejected' && !empty(\$address['membership_certificate_rejection_reason']))\n                                            <p class=\"text--danger mb-2\">@lang('Rejected reason'): {{ \$address['membership_certificate_rejection_reason'] }}</p>\n                                        @endif\n                                        <input class=\"form--control\" name=\"grower_certificate\" type=\"file\" accept=\".pdf,.jpg,.jpeg,.png\" @if(!\$certificateUploaded) required @endif>",
            $contents
        );
    }
    return $contents;
});

$adminDetail = $root . '\\resources\\views\\admin\\users\\detail.blade.php';
patch_file($adminDetail, function ($contents) {
    if (strpos($contents, '$certificateStatus') === false) {
        $contents = str_replace(
            "@section('panel')\n",
            "@section('panel')\n    @php\n        \$memberAddress = (array) \$user->address;\n        \$certificatePath = \$memberAddress['membership_certificate_path'] ?? null;\n        \$certificateUploaded = !empty(\$certificatePath);\n        \$certificateStatus = \$memberAddress['membership_certificate_status'] ?? (\$certificateUploaded ? 'pending' : 'missing');\n        \$certificateStatusClass = match(\$certificateStatus) {\n            'approved' => 'success',\n            'rejected' => 'danger',\n            'pending' => 'warning',\n            default => 'secondary',\n        };\n    @endphp\n",
            $contents
        );
    }

    if (strpos($contents, 'NATCODEV Membership Certificate Review') === false) {
        $card = <<<'BLADE'

        <div class="col-12">
            <div class="card border--warning">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0"><i class="las la-certificate"></i> @lang('NATCODEV Membership Certificate Review')</h5>
                    <span class="badge badge--{{ $certificateStatusClass }}">{{ __(ucwords(str_replace('_', ' ', $certificateStatus))) }}</span>
                </div>
                <div class="card-body">
                    @if($certificateUploaded)
                        <p class="mb-3">@lang('The member uploaded a NATCODEV Growers Certificate or NATCODEV-issued cooperative certificate. Review the document before approving membership completion.')</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.users.certificate.view', $user->id) }}" target="_blank" class="btn btn--dark">
                                <i class="las la-eye"></i> @lang('View Certificate')
                            </a>
                            @if($certificateStatus !== 'approved')
                                <form action="{{ route('admin.users.certificate.approve', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn--success"><i class="las la-check-circle"></i> @lang('Approve Certificate')</button>
                                </form>
                            @endif
                        </div>

                        @if($certificateStatus === 'rejected' && !empty($memberAddress['membership_certificate_rejection_reason']))
                            <div class="alert alert-danger mt-3 mb-0">
                                <strong>@lang('Rejected reason'):</strong> {{ $memberAddress['membership_certificate_rejection_reason'] }}
                            </div>
                        @endif

                        @if($certificateStatus !== 'approved')
                            <form action="{{ route('admin.users.certificate.reject', $user->id) }}" method="POST" class="mt-3">
                                @csrf
                                <label class="form-label">@lang('Reject with reason')</label>
                                <div class="input-group">
                                    <input type="text" name="reason" class="form-control" placeholder="@lang('State why this certificate was rejected')" required>
                                    <button type="submit" class="btn btn--danger"><i class="las la-times-circle"></i> @lang('Reject')</button>
                                </div>
                            </form>
                        @endif
                    @else
                        <div class="alert alert-warning mb-0">
                            @lang('No NATCODEV membership certificate has been uploaded yet. The member must upload this compulsory document from their profile.')
                        </div>
                    @endif
                </div>
            </div>
        </div>
BLADE;

        $contents = str_replace("    <div class=\"row gy-4\">\n", "    <div class=\"row gy-4\">\n" . $card . "\n", $contents);
    }
    return $contents;
});

echo "CERTIFICATE_REVIEW_VIEWS_PATCHED\n";
