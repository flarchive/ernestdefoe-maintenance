<?php

namespace ErnestDefoe\Maintenance\Console;

use Flarum\Console\AbstractCommand;
use Illuminate\Database\ConnectionInterface;

/**
 * Puts `tags.discussion_count` back in step with reality.
 *
 * That column is a stored counter maintained by `+= $delta` in flarum/tags'
 * UpdateTagMetadata listener, and it is never recomputed. Anything that tags a
 * discussion outside those domain events — an import, a restore, direct SQL, a
 * bulk move — drifts it permanently and silently. It does not self-heal, and
 * flarum/tags ships no command to repair it.
 *
 * On this forum nine tags had drifted, every one of them UNDER-counting: a
 * category showing "0 discussions" beside "10 posts", which is how it was
 * noticed at all.
 */
class RecountTagsCommand extends AbstractCommand
{
    public function __construct(
        protected ConnectionInterface $db
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tags:recount')
            ->setDescription('Recompute tags.discussion_count from the discussions actually tagged.');
    }

    protected function fire(): int
    {
        if (! $this->db->getSchemaBuilder()->hasTable('tags')) {
            $this->info('flarum/tags is not installed — nothing to recount.');

            return 0;
        }

        $drifted = $this->drifted();

        if ($drifted === []) {
            $this->info('All tag counts are already correct.');

            return 0;
        }

        foreach ($drifted as $row) {
            $this->info(sprintf('  %-28s %d → %d', $row->name, $row->stored, $row->actual));
            $this->db->table('tags')->where('id', $row->id)->update(['discussion_count' => $row->actual]);
        }

        $this->info(sprintf('Recounted %d tag(s).', count($drifted)));

        return 0;
    }

    /** @return array<int, object{id: int, name: string, stored: int, actual: int}> tags whose stored count disagrees with the data */
    protected function drifted(): array
    {
        /*
         * 🚨 Private and hidden discussions do not count.
         *
         * UpdateTagMetadata's comment says exactly that, though its increment
         * only guards is_private — a hidden discussion is decremented when it is
         * hidden instead. This pair of conditions is what agrees with the stored
         * value on every tag that has NOT drifted, which is what makes it the
         * right rule rather than merely a plausible one.
         *
         * Built with the query builder, not raw SQL: it adds the table prefix,
         * and the same query runs on MySQL, MariaDB, PostgreSQL and SQLite. The
         * raw version used MySQL-only syntax (an alias in UPDATE ... SET, a
         * HAVING without GROUP BY, is_private = 0 on a boolean) and failed on
         * the other three.
         */
        $actual = $this->db->table('discussion_tag')
            ->join('discussions', 'discussions.id', '=', 'discussion_tag.discussion_id')
            ->whereColumn('discussion_tag.tag_id', 'tags.id')
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->selectRaw('count(*)');

        return $this->db->table('tags')
            ->select('id', 'name', 'discussion_count as stored')
            ->selectSub($actual, 'actual')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => (object) [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'stored' => (int) $row->stored,
                'actual' => (int) $row->actual,
            ])
            ->filter(fn (object $row) => $row->stored !== $row->actual)
            ->values()
            ->all();
    }
}
