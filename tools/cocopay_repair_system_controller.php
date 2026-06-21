<?php

$file = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Http\\Controllers\\Admin\\SystemController.php';

$code = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function systemInfo()
    {
        $laravelVersion = app()->version();
        $timeZone = config('app.timezone');
        $pageTitle = 'Application Information';

        return view('admin.system.info', compact('pageTitle', 'laravelVersion', 'timeZone'));
    }

    public function optimize()
    {
        $pageTitle = 'Clear System Cache';

        return view('admin.system.optimize', compact('pageTitle'));
    }

    public function optimizeClear()
    {
        Artisan::call('optimize:clear');

        $notify[] = ['success', 'Cache cleared successfully'];
        return back()->withNotify($notify);
    }

    public function systemServerInfo()
    {
        $currentPHP = phpversion();
        $pageTitle = 'Server Information';
        $serverDetails = $_SERVER;

        return view('admin.system.server', compact('pageTitle', 'currentPHP', 'serverDetails'));
    }

    public function systemUpdate()
    {
        $pageTitle = 'System Update';
        $updates = UpdateLog::orderBy('id', 'desc')->get();

        return view('admin.system.update', compact('pageTitle', 'updates'));
    }

    public function updateUpload(Request $request)
    {
        $request->validate([
            'purchase_code' => 'required|string',
            'envato_username' => 'required|string|max:255',
            'file' => 'required|file|mimes:zip',
        ]);

        $notify[] = ['warning', 'Automatic vendor patch upload is disabled for this customized NATCODEV deployment. Please apply updates manually after a full backup and compatibility review.'];
        return back()->withNotify($notify);
    }
}
PHP;

file_put_contents($file, $code);

echo "SYSTEM_CONTROLLER_REPAIRED\n";
