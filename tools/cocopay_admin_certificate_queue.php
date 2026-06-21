<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$controller = $root . '\\app\\Http\\Controllers\\Admin\\ManageUsersController.php';
$code = file_get_contents($controller);

$method = <<<'PHP'
    public function certificateApplications()
    {
        $status = request('status', 'pending');
        $allowedStatuses = ['pending', 'approved', 'rejected', 'missing', 'all'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $pageTitle = 'NATCODEV Certificate Validation';
        $users = User::query();

        if (request()->search) {
            $users->searchable(['username', 'firstname', 'lastname', 'email', 'mobile', 'account_number']);
        }

        if ($status === 'missing') {
            $users->where(function ($query) {
                $query->whereNull('address->membership_certificate_path')
                    ->orWhere('address->membership_certificate_path', '');
            });
        } elseif ($status !== 'all') {
            $users->where('address->membership_certificate_status', $status)
                ->whereNotNull('address->membership_certificate_path');
        } else {
            $users->whereNotNull('address->membership_certificate_path');
        }

        $users = $users->with('branch:id,name')->orderBy('id', 'desc')->paginate(getPaginate());

        $counts = [
            'pending' => User::where('address->membership_certificate_status', 'pending')->whereNotNull('address->membership_certificate_path')->count(),
            'approved' => User::where('address->membership_certificate_status', 'approved')->whereNotNull('address->membership_certificate_path')->count(),
            'rejected' => User::where('address->membership_certificate_status', 'rejected')->whereNotNull('address->membership_certificate_path')->count(),
            'missing' => User::where(function ($query) {
                $query->whereNull('address->membership_certificate_path')
                    ->orWhere('address->membership_certificate_path', '');
            })->count(),
        ];

        return view('admin.users.certificates', compact('pageTitle', 'users', 'status', 'counts'));
    }

PHP;

if (!str_contains($code, 'public function certificateApplications()')) {
    $code = str_replace("    public function viewCertificate(\$id)\n", $method . "    public function viewCertificate(\$id)\n", $code);
}

file_put_contents($controller, $code);

$routes = $root . '\\routes\\admin.php';
$routeCode = file_get_contents($routes);
if (!str_contains($routeCode, "certificates', 'certificateApplications")) {
    $routeCode = str_replace(
        "        Route::get('owes/loan/{userId}', 'owesLoan')->name('owes.loan');\n\n        Route::get('detail/{id}', 'detail')->name('detail');",
        "        Route::get('owes/loan/{userId}', 'owesLoan')->name('owes.loan');\n        Route::get('certificates', 'certificateApplications')->name('certificates');\n\n        Route::get('detail/{id}', 'detail')->name('detail');",
        $routeCode
    );
    file_put_contents($routes, $routeCode);
}

$provider = $root . '\\app\\Providers\\AppServiceProvider.php';
$providerCode = file_get_contents($provider);
if (!str_contains($providerCode, 'certificatePendingUsersCount')) {
    $providerCode = str_replace(
        "                'kycPendingUsersCount'       => User::kycPending()->count(),\n",
        "                'kycPendingUsersCount'       => User::kycPending()->count(),\n                'certificatePendingUsersCount' => User::where('address->membership_certificate_status', 'pending')->whereNotNull('address->membership_certificate_path')->count(),\n",
        $providerCode
    );
    file_put_contents($provider, $providerCode);
}

$sidenav = $root . '\\resources\\views\\admin\\partials\\sidenav.blade.php';
$sideCode = file_get_contents($sidenav);
$sideCode = str_replace(
    '$bannedUsersCount > 0 || $emailUnverifiedUsersCount > 0 || $mobileUnverifiedUsersCount > 0 || $kycUnverifiedUsersCount > 0 || $kycPendingUsersCount > 0',
    '$bannedUsersCount > 0 || $emailUnverifiedUsersCount > 0 || $mobileUnverifiedUsersCount > 0 || $kycUnverifiedUsersCount > 0 || $kycPendingUsersCount > 0 || $certificatePendingUsersCount > 0',
    $sideCode
);

if (!str_contains($sideCode, "route('admin.users.certificates')")) {
    $item = <<<'BLADE'

                                @can('admin.users.certificates')
                                    <li class="sidebar-menu-item {{ menuActive('admin.users.certificates') }}">
                                        <a class="nav-link" href="{{ route('admin.users.certificates') }}">
                                            <i class="menu-icon las la-dot-circle"></i>
                                            <span class="menu-title">@lang('Certificate Validation')</span>
                                            @if ($certificatePendingUsersCount)
                                                <span class="menu-badge pill bg--danger ms-auto">{{ $certificatePendingUsersCount }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endcan
BLADE;
    $sideCode = str_replace("                                @can('admin.users.kyc.pending')\n", $item . "\n\n                                @can('admin.users.kyc.pending')\n", $sideCode);
    file_put_contents($sidenav, $sideCode);
}

$view = $root . '\\resources\\views\\admin\\users\\certificates.blade.php';
if (!file_exists($view)) {
    file_put_contents($view, <<<'BLADE'
@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-4 mb-4">
        @foreach ([
            'pending' => ['label' => 'Pending Review', 'class' => 'warning', 'icon' => 'la-hourglass-half'],
            'approved' => ['label' => 'Approved Members', 'class' => 'success', 'icon' => 'la-user-check'],
            'rejected' => ['label' => 'Rejected Documents', 'class' => 'danger', 'icon' => 'la-user-times'],
            'missing' => ['label' => 'Missing Certificate', 'class' => 'secondary', 'icon' => 'la-file-upload'],
        ] as $key => $meta)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('admin.users.certificates', ['status' => $key]) }}" class="text-decoration-none">
                    <div class="card b-radius--10 border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase">{{ __($meta['label']) }}</span>
                                <h3 class="mb-0 text--{{ $meta['class'] }}">{{ $counts[$key] ?? 0 }}</h3>
                            </div>
                            <i class="las {{ $meta['icon'] }} la-3x text--{{ $meta['class'] }}"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card b-radius--10">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">@lang('NATCODEV Membership Certificate Queue')</h5>
                    <p class="mb-0 text-muted">@lang('Review certificates before members can access cooperative loans.')</p>
                </div>
                <div class="btn-group">
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'missing' => 'Missing', 'all' => 'All Uploaded'] as $key => $label)
                        <a href="{{ route('admin.users.certificates', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn--primary' : 'btn-outline--primary' }}">{{ __($label) }}</a>
                    @endforeach
                </div>
            </div>

            <div class="table-responsive--md table-responsive">
                <table class="table--light style--two table">
                    <thead>
                        <tr>
                            <th>@lang('Member')</th>
                            <th>@lang('Branch')</th>
                            <th>@lang('Certificate Status')</th>
                            <th>@lang('Uploaded')</th>
                            <th>@lang('Reviewed')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $address = (array) $user->address;
                                $certificatePath = $address['membership_certificate_path'] ?? null;
                                $certificateStatus = $address['membership_certificate_status'] ?? ($certificatePath ? 'pending' : 'missing');
                                $badge = match ($certificateStatus) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-bold d-block">{{ __($user->fullname) }}</span>
                                    <a href="{{ route('admin.users.detail', $user->id) }}"><span>@</span>{{ $user->username }}</a>
                                    <span class="d-block small text-muted">{{ $user->email }}</span>
                                </td>
                                <td>{{ __(@$user->branch->name ?? 'Online') }}</td>
                                <td>
                                    <span class="badge badge--{{ $badge }}">{{ __(ucwords($certificateStatus)) }}</span>
                                    @if ($certificateStatus === 'rejected' && !empty($address['membership_certificate_rejection_reason']))
                                        <span class="d-block small text--danger mt-1">{{ __($address['membership_certificate_rejection_reason']) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($address['membership_certificate_uploaded_at']))
                                        {{ showDateTime($address['membership_certificate_uploaded_at']) }}
                                    @elseif ($certificatePath)
                                        @lang('On file')
                                    @else
                                        <span class="text-muted">@lang('Not uploaded')</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($address['membership_certificate_reviewed_at']))
                                        {{ showDateTime($address['membership_certificate_reviewed_at']) }}
                                    @else
                                        <span class="text-muted">@lang('Not reviewed')</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.users.detail', $user->id) }}" class="btn btn-sm btn-outline--primary">
                                            <i class="las la-desktop"></i>@lang('Details')
                                        </a>
                                        @if ($certificatePath)
                                            <a href="{{ route('admin.users.certificate.view', $user->id) }}" target="_blank" class="btn btn-sm btn-outline--dark">
                                                <i class="las la-file-alt"></i>@lang('View')
                                            </a>
                                            @if ($certificateStatus !== 'approved')
                                                <form action="{{ route('admin.users.certificate.approve', $user->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline--success">
                                                        <i class="las la-check-circle"></i>@lang('Approve')
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer py-4">
                {{ paginateLinks($users) }}
            </div>
        @endif
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Username / Email" />
@endpush
BLADE);
}

echo "ADMIN_CERTIFICATE_QUEUE_READY\n";
