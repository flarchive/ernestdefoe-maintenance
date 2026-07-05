<?php

/*
 * This file is part of ernestdefoe/maintenance.
 *
 * Run migrations and publish assets from the Flarum admin dashboard.
 */

use ErnestDefoe\Maintenance\Api\Controller;
use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // Both controllers assert admin as their first action.
    (new Extend\Routes('api'))
        ->post('/maintenance/migrate', 'maintenance.migrate', Controller\RunMigrationsController::class)
        ->post('/maintenance/assets', 'maintenance.assets', Controller\PublishAssetsController::class),
];
