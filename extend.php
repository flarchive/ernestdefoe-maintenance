<?php

/*
 * This file is part of ernestdefoe/maintenance.
 *
 * Run migrations and publish assets from the Flarum admin dashboard.
 */

use ErnestDefoe\Maintenance\Api\Controller;
use ErnestDefoe\Maintenance\Console\RecountTagsCommand;
use ErnestDefoe\Maintenance\Provider\FormatterHealthProvider;
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

    /*
     * Keep post rendering alive across a cache clear.
     *
     * The formatter caches a serialized renderer that depends on a generated
     * class file in storage/formatter. On a site where the default cache is
     * Redis but the formatter has its own file store, a cache clear flushes
     * one half and deletes the other, and every render 500s from then on.
     * This makes the formatter rebuild itself instead.
     */
    (new Extend\ServiceProvider())
        ->register(FormatterHealthProvider::class),
];
