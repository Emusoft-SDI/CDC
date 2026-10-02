<?php
declare(strict_types=1);

namespace Natcodev\Modules\Registry\Http;

use Natcodev\Support\View;

final class HomeController
{
    public function __invoke(): void
    {
        View::render(__DIR__ . '/../Views/index.php', [
            'logo' => app_primary_logo_url(),
            'year' => date('Y'),
            'roles' => [
                ['title' => 'Coconut Grower', 'text' => 'Join the NATCODEV coconut registry as a farmer or grower.', 'href' => '../apply.php?type=farmer', 'icon' => 'fa-seedling', 'primary' => true],
                ['title' => 'Commercial Outgrower', 'text' => 'Register larger coconut production or outgrower operations.', 'href' => '../apply.php?type=outgrower', 'icon' => 'fa-tractor', 'primary' => false],
                ['title' => 'Cooperative', 'text' => 'Register a coconut farmers cooperative or group.', 'href' => '../apply.php?type=cooperative', 'icon' => 'fa-people-group', 'primary' => false],
                ['title' => 'Input or Service Provider', 'text' => 'Start provider onboarding and accreditation.', 'href' => '../provider/index.php', 'icon' => 'fa-handshake', 'primary' => false],
            ],
        ]);
    }
}
