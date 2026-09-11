<?php

/*
 * This file is part of ernestdefoe/maintenance.
 */

namespace ErnestDefoe\Maintenance\Provider;

use ErnestDefoe\Maintenance\Formatter\SelfHealingFormatter;
use Flarum\Foundation\AbstractServiceProvider;

/**
 * Swap Flarum's formatter for one that repairs a stale renderer cache instead
 * of serving 500s until someone notices.
 *
 * @see SelfHealingFormatter for the failure being guarded against.
 */
class FormatterHealthProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->extend('flarum.formatter', function ($formatter) {
            // Re-use whatever core constructed the formatter with, read off the
            // instance itself, rather than rebuilding the arguments here. Core
            // has changed how it wires the cache and the cache directory before
            // now, and a copy of that wiring would rot silently.
            $reflection = new \ReflectionObject($formatter);

            $borrow = function (string $name) use ($reflection, $formatter) {
                if (! $reflection->hasProperty($name)) {
                    return null;
                }
                /*
                 * 🚨 No setAccessible(). It has had no effect since PHP 8.1 —
                 * reflection reads a private property without it — and PHP 8.5
                 * deprecated the method. Under the deprecation-to-exception
                 * handling a Flarum install runs with, that call THREW while
                 * the formatter was being resolved, and every caller that
                 * guards its own render swallowed the throw and fell back to
                 * escaped plain text. On ernestdefoe.online that meant every
                 * Page Builder markdown field on the storefront rendered as
                 * literal markdown — hashes, asterisks and unrendered image
                 * syntax — with nothing in the log to say why.
                 */
                return $reflection->getProperty($name)->getValue($formatter);
            };

            $cache = $borrow('cache');
            $cacheDir = $borrow('cacheDir');

            /*
             * 🚨 The callbacks are the whole formatter. Every extension that
             * touches formatting — markdown, bbcode, emoji, mentions, and every
             * one of ours — contributes through addConfigurationCallback() and
             * friends AFTER construction. A brand-new Formatter has all four
             * arrays empty, so swapping the instance for one silently threw
             * away the entire formatting pipeline: `**bold**` stayed literal,
             * and stored `<IMG>` tags had no template left to render with, so
             * every image on the site vanished from every post.
             *
             * Nothing surfaced it. The formatter did not throw; it rendered
             * correctly for a configuration that no longer had anything in it.
             */
            $callbacks = [
                'configurationCallbacks' => $borrow('configurationCallbacks'),
                'parsingCallbacks' => $borrow('parsingCallbacks'),
                'unparsingCallbacks' => $borrow('unparsingCallbacks'),
                'renderingCallbacks' => $borrow('renderingCallbacks'),
            ];

            /*
             * If core's shape ever stops matching, leave the original in place
             * rather than substituting something half-built. Every one of these
             * has to be readable — a missing callback array is exactly the
             * "half-built" case this guard exists for, and it was the one thing
             * the guard did not check.
             */
            if ($cache === null || ! is_string($cacheDir)) {
                return $formatter;
            }

            foreach ($callbacks as $value) {
                if (! is_array($value)) {
                    return $formatter;
                }
            }

            $healing = new SelfHealingFormatter($cache, $cacheDir, $borrow('config'));

            // Carry the pipeline over to the replacement.
            $target = new \ReflectionObject($healing);
            foreach ($callbacks as $name => $value) {
                if ($target->hasProperty($name)) {
                    $target->getProperty($name)->setValue($healing, $value);
                }
            }

            return $healing;
        });
    }
}
