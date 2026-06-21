<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/crystal_sky/user/auth/register.blade.php';

$blade = <<<'BLADE'
@extends($activeTemplate . 'layouts.app')
@section('app')
    @php
        $policyPages = getContent('policy_pages.element', orderById: true);
        $assetBase = asset('assets/images/frontend/natcodev');
        $oldState = old('state_id');
        $oldLga = old('lga_id');
    @endphp

    <style>
        :root {
            --nat-green-950: #062c1f;
            --nat-green-900: #083a28;
            --nat-green-800: #0c5136;
            --nat-green-700: #0f6f45;
            --nat-gold: #d8a846;
            --nat-gold-2: #f1cb73;
            --nat-cream: #fff9ed;
            --nat-ink: #17231d;
            --nat-muted: #657268;
            --nat-line: rgba(8, 58, 40, .14);
        }

        body { background: #fbfbf6; }

        .nat-register {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(420px, .82fr) minmax(520px, 1.18fr);
            background: #fbfbf6;
            color: var(--nat-ink);
        }

        .nat-register-media {
            position: sticky;
            top: 0;
            min-height: 100vh;
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            background-image: linear-gradient(180deg, rgba(6, 44, 31, .45), rgba(6, 44, 31, .94)), url('{{ $assetBase }}/dwarf-seedlings.png');
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }

        .nat-register-media::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 8px;
            background: linear-gradient(90deg, var(--nat-gold), #fff1b6, var(--nat-green-700));
        }

        .nat-register-brand {
            position: relative;
            z-index: 1;
            width: 118px;
            height: 118px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .94);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 44px rgba(0, 0, 0, .18);
            overflow: hidden;
        }

        .nat-register-brand img {
            width: 94px;
            height: 94px;
            object-fit: contain;
            border-radius: 999px;
        }

        .nat-register-story {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }

        .nat-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 8px 13px;
            border: 1px solid rgba(241, 203, 115, .52);
            border-radius: 999px;
            color: #ffe7a5;
            background: rgba(255, 255, 255, .08);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0;
        }

        .nat-register-story h1 {
            margin: 20px 0 16px;
            color: #fff;
            font-size: clamp(38px, 5vw, 62px);
            line-height: 1;
            letter-spacing: 0;
        }

        .nat-register-story p {
            color: rgba(255, 255, 255, .82);
            font-size: 17px;
            line-height: 1.75;
            margin: 0;
        }

        .nat-onboarding-list {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .nat-onboarding-item {
            display: flex;
            gap: 12px;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .17);
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(12px);
        }

        .nat-onboarding-item i {
            color: var(--nat-gold-2);
            font-size: 22px;
            margin-top: 2px;
        }

        .nat-onboarding-item strong {
            display: block;
            color: #fff;
            margin-bottom: 2px;
        }

        .nat-onboarding-item span {
            color: rgba(255, 255, 255, .76);
            font-size: 13px;
            line-height: 1.45;
        }

        .nat-register-panel {
            min-height: 100vh;
            padding: 38px min(5vw, 72px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nat-register-card {
            width: min(100%, 760px);
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 28px 80px rgba(6, 44, 31, .1);
        }

        .nat-register-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            margin-bottom: 24px;
        }

        .nat-register-logo img {
            max-width: 150px;
            max-height: 74px;
            object-fit: contain;
        }

        .nat-home-link,
        .nat-register-card a {
            color: var(--nat-green-700);
            font-weight: 800;
        }

        .nat-home-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .nat-register-card h2 {
            margin: 0 0 10px;
            color: var(--nat-green-950);
            font-size: 34px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .nat-register-card .lead {
            color: var(--nat-muted);
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .nat-register-card .form--label {
            color: var(--nat-green-950);
            font-weight: 800;
            margin-bottom: 9px;
        }

        .nat-register-card .form--control,
        .nat-register-card .form-control {
            min-height: 52px;
            border-radius: 8px;
            border: 1px solid var(--nat-line);
            background: #fbfbf6;
            color: var(--nat-ink);
        }

        .nat-register-card .form--control:focus,
        .nat-register-card .form-control:focus {
            border-color: var(--nat-gold);
            box-shadow: 0 0 0 4px rgba(216, 168, 70, .16);
            background: #fff;
        }

        .nat-register-card .btn--base {
            min-height: 52px;
            border-radius: 8px;
            border: 0;
            color: var(--nat-green-950);
            background: linear-gradient(135deg, var(--nat-gold-2), var(--nat-gold)) !important;
            font-weight: 900;
            box-shadow: 0 18px 36px rgba(216, 168, 70, .24);
        }

        .nat-field-note {
            display: block;
            margin-top: 7px;
            color: var(--nat-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .nat-certificate-box {
            padding: 16px;
            border: 1px solid rgba(216, 168, 70, .3);
            border-radius: 12px;
            background: var(--nat-cream);
        }

        .nat-certificate-box strong {
            display: block;
            color: var(--nat-green-950);
            margin-bottom: 4px;
        }

        .nat-login-box {
            margin-top: 18px;
            padding-top: 20px;
            border-top: 1px solid var(--nat-line);
            text-align: center;
            color: var(--nat-muted);
        }

        @media (max-width: 1080px) {
            .nat-register { grid-template-columns: 1fr; }
            .nat-register-media {
                position: relative;
                min-height: auto;
                padding: 32px;
            }
            .nat-register-panel {
                min-height: auto;
                padding: 28px 18px 48px;
            }
        }

        @media (max-width: 640px) {
            .nat-register-card { padding: 24px; }
            .nat-register-top {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <main class="nat-register">
        <section class="nat-register-media">
            <a class="nat-register-brand" href="{{ route('home') }}" aria-label="@lang('NATCODEV home')">
                <img src="{{ siteLogo('dark') }}" alt="@lang('NATCODEV Cooperative Society')">
            </a>

            <div class="nat-register-story">
                <span class="nat-eyebrow"><i class="las la-seedling"></i>@lang('Cooperative member onboarding')</span>
                <h1>@lang('Join the NATCODEV coconut farmers network.')</h1>
                <p>@lang('Create your member account for wallet deposits, cooperative savings, farm credit, grower certification, and coconut value-chain support.')</p>
                <div class="nat-onboarding-list">
                    <div class="nat-onboarding-item"><i class="las la-map-marked-alt"></i><div><strong>@lang('Nigeria state and LGA')</strong><span>@lang('Select your state first, then choose the matching local government area from the database.')</span></div></div>
                    <div class="nat-onboarding-item"><i class="las la-certificate"></i><div><strong>@lang('NATCODEV growers certificate')</strong><span>@lang('Upload a NATCODEV-issued growers or cooperative certificate to support eligibility review.')</span></div></div>
                    <div class="nat-onboarding-item"><i class="las la-wallet"></i><div><strong>@lang('Member finance workspace')</strong><span>@lang('After approval, manage savings, deposits, loans, transactions, and support in one dashboard.')</span></div></div>
                </div>
            </div>
        </section>

        <section class="nat-register-panel">
            <div class="nat-register-card">
                <div class="nat-register-top">
                    <a class="nat-register-logo" href="{{ route('home') }}"><img src="{{ siteLogo('dark') }}" alt="@lang('NATCODEV logo')"></a>
                    <a class="nat-home-link" href="{{ route('home') }}"><i class="las la-arrow-left"></i>@lang('Back to site')</a>
                </div>

                <h2>@lang('Create Member Account')</h2>
                <p class="lead">@lang('Register as a NATCODEV Coconut Farmers Cooperative member. Nigeria is the default country, and your state/LGA and growers certificate are required.')</p>

                <form action="{{ route('user.register') }}" method="POST" enctype="multipart/form-data" class="verify-gcaptcha">
                    @csrf
                    <div class="row">
                        @if (session()->get('reference') != null && $general->modules->referral_system)
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="referenceBy" class="form--label">@lang('Referred by')</label>
                                    <input type="text" name="referBy" id="referenceBy" class="form--control" value="{{ session()->get('reference') }}" readonly>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username" class="form--label">@lang('Username')</label>
                                <input type="text" class="form--control checkUser" name="username" value="{{ old('username') }}" id="username" autocomplete="username" required>
                                <small class="text--danger usernameExist"></small>
                                <span class="nat-field-note">@lang('Use lowercase letters, numbers, or underscore. Minimum 6 characters.')</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form--label">@lang('Email Address')</label>
                                <input type="email" name="email" class="form--control checkUser" id="email" value="{{ old('email') }}" autocomplete="email" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="country" class="form--label">@lang('Country')</label>
                                <select name="country" id="country" class="form--control" required>
                                    @foreach ($countries as $key => $country)
                                        <option data-mobile_code="{{ $country->dial_code }}" value="{{ $country->country }}" data-code="{{ $key }}" @selected(old('country', 'Nigeria') == $country->country || $key == 'NG')>
                                            {{ __($country->country) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="nat-field-note">@lang('Default country for NATCODEV cooperative registration is Nigeria.')</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="form--label">@lang('Mobile')</label>
                                <div class="input-group custom-input-group">
                                    <span class="input-group-text mobile-code"></span>
                                    <input type="number" name="mobile" value="{{ old('mobile') }}" id="phone" class="form-control form--control checkUser" autocomplete="tel-national" required>
                                </div>
                                <small class="text--danger mobileExist"></small>
                                <input type="hidden" name="mobile_code">
                                <input type="hidden" name="country_code">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="state_id" class="form--label">@lang('State')</label>
                                <select name="state_id" id="state_id" class="form--control" required>
                                    <option value="">@lang('Select State')</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" @selected((string) old('state_id') === (string) $state->id)>{{ __($state->state_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lga_id" class="form--label">@lang('Local Government Area')</label>
                                <select name="lga_id" id="lga_id" class="form--control" data-old="{{ old('lga_id') }}" required>
                                    <option value="">@lang('Select state first')</option>
                                </select>
                                <span class="nat-field-note">@lang('LGA options load automatically after you select your state.')</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="yourPassword" class="form--label">@lang('Password')</label>
                                <input name="password" id="yourPassword" type="password" class="form--control @if ($general->secure_password) secure-password @endif" autocomplete="new-password" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirmPassword" class="form--label">@lang('Confirm Password')</label>
                                <input name="password_confirmation" id="confirmPassword" type="password" class="form--control" autocomplete="new-password" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group nat-certificate-box">
                                <label for="grower_certificate" class="form--label">@lang('NATCODEV Growers Certificate')</label>
                                <input id="grower_certificate" name="grower_certificate" type="file" class="form--control" accept=".pdf,.jpg,.jpeg,.png" required>
                                <strong>@lang('Required for cooperative membership review')</strong>
                                <span class="nat-field-note">@lang('Upload your NATCODEV growers certificate or a certificate issued by NATCODEV to your cooperative. Accepted: PDF, JPG, PNG. Max 5MB.')</span>
                            </div>
                        </div>

                        <x-captcha />

                        @if ($general->agree)
                            <div class="col-12">
                                <div class="form-group d-flex">
                                    <div class="form--check">
                                        <input class="form-check-input" type="checkbox" name="agree" @checked(old('agree')) id="remember">
                                    </div>
                                    <div class="terms px-2">
                                        <label class="form-check-label" for="remember">@lang('I agree with')</label>
                                        @foreach ($policyPages as $policy)
                                            <a class="text--base footer-menu__link" href="{{ route('policy.pages', [slug($policy->data_values->title), $policy->id]) }}" target="_blank">{{ __($policy->data_values->title) }}</a>@if (!$loop->last),@endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-12">
                            <div class="form-group">
                                <button type="submit" id="recaptcha" class="btn btn--base w-100">@lang('Create NATCODEV member account')</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="nat-login-box">
                    <span>@lang('Already registered?')</span>
                    <a href="{{ route('user.login') }}">@lang('Sign in to member workspace')</a>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="existModalCenter" tabindex="-1" role="dialog" aria-labelledby="existModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="existModalLongTitle">@lang('Account already exists')</h5>
                    <span type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </span>
                </div>
                <div class="modal-body">
                    <h6 class="text-center">@lang('This email already belongs to a NATCODEV member account. Please login instead.')</h6>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                    <a href="{{ route('user.login') }}" class="btn btn--base btn-sm">@lang('Login')</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@if ($general->secure_password)
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif

@push('script')
    <script>
        "use strict";
        (function($) {
            function syncCountry() {
                const selected = $('select[name=country] :selected');
                $('input[name=mobile_code]').val(selected.data('mobile_code'));
                $('input[name=country_code]').val(selected.data('code'));
                $('.mobile-code').text('+' + selected.data('mobile_code'));
            }

            function loadLgas(stateId, selectedLga) {
                const lgaSelect = $('#lga_id');
                lgaSelect.html(`<option value="">@lang('Loading LGAs...')</option>`);

                if (!stateId) {
                    lgaSelect.html(`<option value="">@lang('Select state first')</option>`);
                    return;
                }

                const url = `{{ route('user.register.lgas', ':state') }}`.replace(':state', stateId);
                $.get(url, function(response) {
                    let options = `<option value="">@lang('Select LGA')</option>`;
                    (response.data || []).forEach(function(lga) {
                        const selected = String(selectedLga || '') === String(lga.id) ? 'selected' : '';
                        options += `<option value="${lga.id}" ${selected}>${lga.lga_name}</option>`;
                    });
                    lgaSelect.html(options);
                }).fail(function() {
                    lgaSelect.html(`<option value="">@lang('Unable to load LGAs')</option>`);
                });
            }

            $(document).ready(function() {
                const nigeria = $('select[name=country] option[data-code=NG]');
                if (!$('select[name=country]').val() && nigeria.length) {
                    nigeria.prop('selected', true);
                }
                syncCountry();
                loadLgas($('#state_id').val(), $('#lga_id').data('old'));
            });

            $('select[name=country]').on('change', syncCountry);
            $('#state_id').on('change', function() {
                loadLgas($(this).val(), null);
            });

            $('.checkUser').on('focusout', function() {
                var url = '{{ route('user.checkUser') }}';
                var value = $(this).val();
                var token = '{{ csrf_token() }}';
                var data = { _token: token };

                if ($(this).attr('name') == 'mobile') {
                    data.mobile = `${$('.mobile-code').text().substr(1)}${value}`;
                }

                if ($(this).attr('name') == 'email') {
                    data.email = value;
                }

                if ($(this).attr('name') == 'username') {
                    data.username = value;
                }

                $.post(url, data, function(response) {
                    if (response.data != false && response.type == 'email') {
                        $('#existModalCenter').modal('show');
                    } else if (response.data != false) {
                        $(`.${response.type}Exist`).text(`This ${response.type} already exists`);
                    } else {
                        $(`.${response.type}Exist`).empty();
                    }
                });
            });
        })(jQuery);
    </script>
@endpush
BLADE;

file_put_contents($file, $blade);
echo "NATCODEV register page updated\n";
