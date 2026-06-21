<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www';
$app = $root . '/cocopay';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS nigeria_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL UNIQUE,
    state_code VARCHAR(10) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS nigeria_lgas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lga_name VARCHAR(100) NOT NULL,
    state_id INT NOT NULL,
    UNIQUE KEY uniq_lga_state (lga_name, state_id),
    INDEX idx_lgas_state (state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$sqlDump = file_get_contents($root . '/CDC/database/db.sql');
foreach (['nigeria_states', 'nigeria_lgas'] as $table) {
    if ((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0) {
        if (preg_match('/INSERT INTO `' . preg_quote($table, '/') . '`.*?;\R/s', $sqlDump, $match)) {
            $statement = str_replace("INSERT INTO `{$table}`", "INSERT IGNORE INTO `{$table}`", $match[0]);
            $pdo->exec($statement);
        }
    }
}

if ((int) $pdo->query("SELECT COUNT(*) FROM nigeria_lgas")->fetchColumn() === 0
    && preg_match('/INSERT INTO ` nigeria_lgas`.*?;\R/s', $sqlDump, $match)
) {
    $values = preg_replace('/^INSERT INTO ` nigeria_lgas` \(`id`, `state_id`, `name`\) VALUES/s', '', trim($match[0]));
    $values = rtrim($values, ';');
    $pdo->exec("INSERT IGNORE INTO nigeria_lgas (id, state_id, lga_name) VALUES {$values}");
}

$pdo->exec("UPDATE general_settings SET base_color='0F6B3D', secondary_color='1F3A2D' LIMIT 1");

$controllerFile = $app . '/core/app/Http/Controllers/User/Auth/RegisterController.php';
$controller = file_get_contents($controllerFile);
if (substr_count($controller, "use Illuminate\\Support\\Facades\\DB;\n") === 0) {
    $controller = str_replace(
        "use Illuminate\\Support\\Facades\\Hash;\n",
        "use Illuminate\\Support\\Facades\\Hash;\nuse Illuminate\\Support\\Facades\\DB;\n",
        $controller
    );
} elseif (substr_count($controller, "use Illuminate\\Support\\Facades\\DB;\n") > 1) {
    $controller = preg_replace('/(use Illuminate\\\\Support\\\\Facades\\\\DB;\R)+/', "use Illuminate\\Support\\Facades\\DB;\n", $controller, 1);
}
$controller = str_replace(
    "        \$mobileCode = @implode(',', \$info['code']);\n        \$countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));\n        return view(\$this->activeTemplate . 'user.auth.register', compact('pageTitle', 'mobileCode', 'countries'));",
    "        \$mobileCode = 'NG';\n        \$countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));\n        \$states     = DB::table('nigeria_states')->orderBy('state_name')->get();\n        return view(\$this->activeTemplate . 'user.auth.register', compact('pageTitle', 'mobileCode', 'countries', 'states'));",
    $controller
);
$controller = str_replace(
    "            'country'      => 'required|in:' . \$countries,\n            'agree'        => \$agree,",
    "            'country'      => 'required|in:' . \$countries,\n            'state_id'     => 'required|exists:nigeria_states,id',\n            'lga_id'       => 'required|exists:nigeria_lgas,id',\n            'agree'        => \$agree,",
    $controller
);
$controller = str_replace(
    "            'state'   => '',\n            'zip'     => '',\n            'country' => isset(\$data['country']) ? \$data['country'] : null,\n            'city'    => '',",
    "            'state'   => DB::table('nigeria_states')->where('id', \$data['state_id'])->value('state_name') ?: '',\n            'state_id' => \$data['state_id'] ?? null,\n            'lga'     => DB::table('nigeria_lgas')->where('id', \$data['lga_id'])->value('lga_name') ?: '',\n            'lga_id'  => \$data['lga_id'] ?? null,\n            'zip'     => '',\n            'country' => 'Nigeria',\n            'city'    => '',",
    $controller
);
if (strpos($controller, 'public function lgasByState') === false) {
    $controller = str_replace(
        "\n    public function registered()\n",
        "\n    public function lgasByState(\$stateId)\n    {\n        \$lgas = DB::table('nigeria_lgas')\n            ->where('state_id', \$stateId)\n            ->orderBy('lga_name')\n            ->get(['id', 'lga_name']);\n\n        return response()->json(['data' => \$lgas]);\n    }\n\n    public function registered()\n",
        $controller
    );
}
file_put_contents($controllerFile, $controller);

$routesFile = $app . '/core/routes/user.php';
$routes = file_get_contents($routesFile);
if (strpos($routes, "register/lgas") === false) {
    $routes = str_replace(
        "        Route::get('register', 'showRegistrationForm')->name('register');\n        Route::post('register', 'register')->middleware('registration.status');",
        "        Route::get('register', 'showRegistrationForm')->name('register');\n        Route::get('register/lgas/{stateId}', 'lgasByState')->name('register.lgas');\n        Route::post('register', 'register')->middleware('registration.status');",
        $routes
    );
    file_put_contents($routesFile, $routes);
}

$registerFile = $app . '/core/resources/views/templates/indigo_fusion/user/auth/register.blade.php';
$register = file_get_contents($registerFile);
$countryBlock = <<<'BLADE'
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required">@lang('Country')</label>
                                <select name="country" class="form--control">
                                    @foreach ($countries as $key => $country)
                                        <option data-mobile_code="{{ $country->dial_code }}"
                                            value="{{ $country->country }}" data-code="{{ $key }}">
                                            {{ __($country->country) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
BLADE;
$newCountryBlock = <<<'BLADE'
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required">@lang('Country')</label>
                                <select name="country" class="form--control">
                                    @foreach ($countries as $key => $country)
                                        <option data-mobile_code="{{ $country->dial_code }}"
                                            value="{{ $country->country }}" data-code="{{ $key }}" @selected($key == 'NG')>
                                            {{ __($country->country) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required">@lang('State')</label>
                                <select name="state_id" class="form--control" required>
                                    <option value="">@lang('Select State')</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ __($state->state_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required">@lang('LGA')</label>
                                <select name="lga_id" class="form--control" data-selected="{{ old('lga_id') }}" required>
                                    <option value="">@lang('Select State First')</option>
                                </select>
                            </div>
                        </div>
BLADE;
$register = str_replace($countryBlock, $newCountryBlock, $register);
$register = str_replace(
    "            @if (\$mobileCode)\n                $(`option[data-code={{ \$mobileCode }}]`).attr('selected', '');\n            @endif",
    "            $(`option[data-code=NG]`).attr('selected', '');",
    $register
);
$oldLocationScript = <<<'JS'
            $('select[name=country]').change(function() {
                $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
                $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
                $('.mobile-code').text('+' + $('select[name=country] :selected').data('mobile_code'));
            });

            $('select[name=country]').change();
JS;
$newLocationScript = <<<'JS'
            $('select[name=country]').change(function() {
                $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
                $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
                $('.mobile-code').text('+' + $('select[name=country] :selected').data('mobile_code'));
            });

            function loadLgas(stateId, selectedLga) {
                const lgaSelect = $('select[name=lga_id]');
                lgaSelect.html(`<option value="">@lang('Loading LGAs...')</option>`);
                if (!stateId) {
                    lgaSelect.html(`<option value="">@lang('Select State First')</option>`);
                    return;
                }
                $.get(`{{ url('/user/register/lgas') }}/${stateId}`, function(response) {
                    let options = `<option value="">@lang('Select LGA')</option>`;
                    response.data.forEach(function(lga) {
                        options += `<option value="${lga.id}" ${String(selectedLga) === String(lga.id) ? 'selected' : ''}>${lga.lga_name}</option>`;
                    });
                    lgaSelect.html(options);
                });
            }

            $('select[name=state_id]').on('change', function() {
                loadLgas($(this).val(), null);
            });

            $('select[name=country]').change();
            loadLgas($('select[name=state_id]').val(), $('select[name=lga_id]').data('selected'));
JS;
$register = str_replace($oldLocationScript, $newLocationScript, $register);
file_put_contents($registerFile, $register);

$cssFile = $app . '/assets/templates/indigo_fusion/css/custom.css';
$css = file_get_contents($cssFile);
$marker = '/* NATCODEV calmer navigation and footer */';
if (strpos($css, $marker) === false) {
    $css .= <<<'CSS'

/* NATCODEV calmer navigation and footer */
.header__bottom {
    background: #ffffff !important;
    border-bottom: 1px solid #e4ebe4 !important;
}
.navbar-collapse,
.main-menu {
    opacity: 1 !important;
    visibility: visible !important;
}
.main-menu {
    display: flex !important;
    flex-wrap: wrap;
    gap: 4px 18px;
    justify-content: center;
}
.main-menu li a {
    color: #24382d !important;
    display: inline-flex !important;
    font-weight: 700;
    padding: 12px 4px !important;
}
.main-menu li a.active,
.main-menu li a:hover {
    color: #0f6b3d !important;
}
.nav-right {
    align-items: center;
    display: flex !important;
    gap: 10px;
}
.footer {
    background: #10251c !important;
    color: #dfe9e2 !important;
}
.footer::before,
.footer::after {
    display: none !important;
}
.footer-widget__title,
.footer a,
.footer p,
.footer-info-list li,
.footer__bottom p {
    color: #f3f7f1 !important;
}
.footer__bottom {
    border-color: rgba(255, 255, 255, .14) !important;
}
.btn--base,
.header-base-button {
    background: #0f6b3d !important;
    border-color: #0f6b3d !important;
}
.btn--base:hover,
.header-base-button:hover {
    background: #0b5731 !important;
    border-color: #0b5731 !important;
}
.natco-member-hero {
    background: linear-gradient(135deg, rgba(15, 80, 50, .95), rgba(25, 54, 42, .9)), url('../../../images/frontend/banner/natcodev-africa-dwarf-coconut-hero.png') center/cover no-repeat !important;
}
.natco-member-hero::after {
    background: #0f6b3d !important;
}
CSS;
    file_put_contents($cssFile, $css);
}

echo "Applied registration location support and calmer UI colors." . PHP_EOL;
