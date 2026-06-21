<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Http\\Controllers\\User\\Auth\\RegisterController.php';

$contents = <<<'PHP'
<?php

namespace App\Http\Controllers\User\Auth;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    use RegistersUsers;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('guest');
        $this->middleware('registration.status')->except('registrationNotAllowed');
    }

    public function showRegistrationForm()
    {
        $pageTitle  = "Register";
        $mobileCode = 'NG';
        $countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $states     = DB::table('nigeria_states')->orderBy('state_name')->get();
        return view($this->activeTemplate . 'user.auth.register', compact('pageTitle', 'mobileCode', 'countries', 'states'));
    }

    protected function validator(array $data)
    {
        $passwordValidation = Password::min(6);

        if (gs('secure_password')) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $agree = gs('agree') ? 'required' : 'nullable';

        $countryData  = (array) json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));

        return Validator::make($data, [
            'email'        => 'required|string|email|unique:users',
            'mobile'       => 'required|integer|regex:/^([0-9]*)$/',
            'password'     => ['required', 'confirmed', $passwordValidation],
            'username'     => 'required|unique:users|min:6',
            'captcha'      => 'sometimes|required',
            'mobile_code'  => 'required|in:' . $mobileCodes,
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'state_id'     => 'required|exists:nigeria_states,id',
            'lga_id'       => [
                'required',
                Rule::exists('nigeria_lgas', 'id')->where(function ($query) use ($data) {
                    return $query->where('state_id', $data['state_id'] ?? 0);
                }),
            ],
            'agree'        => $agree,
        ]);
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $request->session()->regenerateToken();

        if (preg_match("/[^a-z0-9_]/", trim($request->username))) {
            $notify[] = ['info', 'Username can contain only small letters, numbers and underscore.'];
            $notify[] = ['error', 'No special character, space or capital letters in username.'];
            return back()->withNotify($notify)->withInput($request->all());
        }

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $exist = User::where('mobile', $request->mobile_code . $request->mobile)->first();

        if ($exist) {
            $notify[] = ['error', 'The mobile number already exists'];
            return back()->withNotify($notify)->withInput();
        }

        event(new Registered($user = $this->create($request->all())));

        $this->guard()->login($user);

        return $this->registered($request, $user) ?: redirect($this->redirectPath());
    }

    protected function create(array $data)
    {
        $user = new User();

        $user->account_number = generateAccountNumber();
        $user->email          = strtolower($data['email']);
        $user->password       = Hash::make($data['password']);
        $user->username       = $data['username'];
        $user->country_code   = $data['country_code'];
        $user->mobile         = $data['mobile_code'] . $data['mobile'];
        $user->kv             = gs('kv') ? Status::NO : Status::YES;
        $user->ev             = gs('ev') ? Status::NO : Status::YES;
        $user->sv             = gs('sv') ? Status::NO : Status::YES;
        $user->ts             = Status::DISABLE;
        $user->tv             = Status::VERIFIED;

        $user->address = [
            'address' => '',
            'state'   => DB::table('nigeria_states')->where('id', $data['state_id'])->value('state_name') ?: '',
            'state_id' => $data['state_id'] ?? null,
            'lga'     => DB::table('nigeria_lgas')->where('id', $data['lga_id'])->value('lga_name') ?: '',
            'lga_id'  => $data['lga_id'] ?? null,
            'zip'     => '',
            'country' => 'Nigeria',
            'city'    => '',
            'membership_certificate_type' => 'NATCODEV Growers Certificate',
            'membership_certificate' => null,
            'membership_certificate_path' => null,
            'membership_certificate_uploaded_at' => null,
            'membership_certificate_status' => 'pending',
        ];

        $referBy = session('reference');

        if ($referBy && gs('modules')->referral_system) {
            $referrer = User::where('username', $referBy)->first();

            if ($referrer) {
                $user->ref_by                    = $referrer->id;
                $user->referral_commission_count = gs('referral_commission_count');
            }
        }

        $user->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $user->id;
        $adminNotification->title     = 'New member registered - certificate pending';
        $adminNotification->click_url = urlPath('admin.users.detail', $user->id);
        $adminNotification->save();

        $ip        = getRealIP();
        $exist     = UserLogin::where('user_ip', $ip)->first();
        $userLogin = new UserLogin();

        if ($exist) {
            $userLogin->longitude    = $exist->longitude;
            $userLogin->latitude     = $exist->latitude;
            $userLogin->city         = $exist->city;
            $userLogin->country_code = $exist->country_code;
            $userLogin->country      = $exist->country;
        } else {
            $info                    = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude    = @implode(',', $info['long']);
            $userLogin->latitude     = @implode(',', $info['lat']);
            $userLogin->city         = @implode(',', $info['city']);
            $userLogin->country_code = @implode(',', $info['code']);
            $userLogin->country      = @implode(',', $info['country']);
        }

        $userAgent          = osBrowser();
        $userLogin->user_id = $user->id;
        $userLogin->user_ip = $ip;
        $userLogin->browser = @$userAgent['browser'];
        $userLogin->os      = @$userAgent['os_platform'];
        $userLogin->save();

        return $user;
    }

    public function lgasByState($stateId)
    {
        $lgas = DB::table('nigeria_lgas')
            ->where('state_id', $stateId)
            ->orderBy('lga_name')
            ->get(['id', 'lga_name']);

        return response()->json(['data' => $lgas]);
    }

    public function registered()
    {
        return to_route('user.home');
    }
}
PHP;

file_put_contents($path, $contents);

echo "RESTORED_REGISTER_CONTROLLER_NO_CERTIFICATE\n";
