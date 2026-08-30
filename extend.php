<?php

/*
 * This file is part of ernestdefoe/maintenance.
 *
 * Run migrations and publish assets from the Flarum admin dashboard.
 */

use ErnestDefoe\Maintenance\Api\Controller;
use ErnestDefoe\Maintenance\Console\RecountTagsCommand;
use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // Both controllers assert admin as their first action.
    (new Extend\Routes('api'))
        ->post('/maintenance/migrate', 'maintenance.migrate', Controller\RunMigrationsController::class)
        ->post('/maintenance/assets', 'maintenance.assets', Controller\PublishAssetsController::class),

    (new Extend\Console())
        ->command(RecountTagsCommand::class)
        /*
         * Nightly, because tags.discussion_count is an incremental counter that
         * never self-heals: flarum/tags adds and subtracts deltas on domain
         * events, so anything that tags a discussion another way — an import, a
         * restore, direct SQL — leaves the number permanently wrong with nothing
         * to notice it. Cheap to run and it no-ops when nothing has drifted.
         *
         * 03:20 rather than on the hour: the hour is where every other
         * scheduled job on a forum already piles up.
         */
        ->schedule(RecountTagsCommand::class, function (Event $event) {
            $event->dailyAt('03:20')->withoutOverlapping();
        }),
];
