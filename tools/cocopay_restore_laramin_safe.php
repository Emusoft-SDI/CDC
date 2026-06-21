<?php

$base = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\vendor\\laramin\\utility\\src';

file_put_contents($base . '\\Onumoti.php', <<<'PHP'
<?php

namespace Laramin\Utility;

class Onumoti
{
    public static function getData()
    {
        return null;
    }

    public static function mySite($site, $className)
    {
        return null;
    }
}
PHP);

file_put_contents($base . '\\GoToCore.php', <<<'PHP'
<?php

namespace Laramin\Utility;

use App\Models\GeneralSetting;
use Closure;

class GoToCore
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function getGeneral()
    {
        return cache()->get('GeneralSetting') ?: GeneralSetting::first();
    }
}
PHP);

file_put_contents($base . '\\UtilityServiceProvider.php', <<<'PHP'
<?php

namespace Laramin\Utility;

use Illuminate\Support\ServiceProvider;

class UtilityServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $router = $this->app['router'];
        $router->aliasMiddleware(VugiChugi::gtc(), GoToCore::class);
        $router->aliasMiddleware(VugiChugi::mdNm(), Utility::class);
    }
}
PHP);

file_put_contents($base . '\\routes.php', <<<'PHP'
<?php

// NATCODEV local deployment: vendor activation routes intentionally disabled.
PHP);

echo "LARAMIN_SAFE_RESTORED\n";
