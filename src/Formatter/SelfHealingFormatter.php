<?php

/*
 * This file is part of ernestdefoe/maintenance.
 */

namespace ErnestDefoe\Maintenance\Formatter;

use Flarum\Formatter\Formatter;
use s9e\TextFormatter\Renderer;

/**
 * A Formatter that repairs itself instead of 500ing forever.
 *
 * ## The failure it fixes
 *
 * The formatter is two halves that must agree:
 *
 *   1. a **serialized renderer object**, cached under `flarum.formatter`;
 *   2. **storage/formatter/Renderer_<hash>.php**, the generated class that
 *      object is an instance of. `getRenderer()` registers an autoloader that
 *      looks in that directory and nowhere else.
 *
 * When the file is deleted while the cache entry survives, unserialize yields
 * `__PHP_Incomplete_Class`, the return type check fails, and **every request
 * that renders a post 500s** — permanently, because `getComponent()` caches
 * with `rememberForever` and nothing invalidates it.
 *
 * ## Why the two halves diverge here
 *
 * On a site using fof/redis, the default cache is Redis but the formatter is
 * given its own **FileStore**. `CacheClearCommand` therefore flushes Redis —
 * which does not contain the formatter entry — and then deletes the class
 * files anyway. The entry outlives the file it depends on. That is not a race;
 * it happens every time, and any other code that flushes the default store and
 * clears that directory reproduces it.
 *
 * ## What this does
 *
 * Catches the TypeError once, drops the stale entry, and rebuilds. The rebuild
 * writes both halves, so the next call is a normal cache hit. One request pays
 * a recompile; nobody sees an outage.
 */
class SelfHealingFormatter extends Formatter
{
    protected function getRenderer(): Renderer
    {
        try {
            return parent::getRenderer();
        } catch (\TypeError $e) {
            // Only handle the specific breakage — anything else is a real bug
            // and must keep surfacing.
            if (! str_contains($e->getMessage(), 'Incomplete_Class')) {
                throw $e;
            }

            // Drop the entry whose class file has gone, then let the parent
            // recompile it. finalize() writes the cache entry AND the generated
            // class file, so both halves are consistent again afterwards.
            $this->flush();

            return parent::getRenderer();
        }
    }
}
