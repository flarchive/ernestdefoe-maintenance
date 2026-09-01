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

            // If core's shape ever stops matching, leave the original in place
            // rather than substituting something half-built.
            if ($cache === null || ! is_string($cacheDir)) {
                return $formatter;
            }

            return new SelfHealingFormatter($cache, $cacheDir, $borrow('config'));
        });
    }
}
