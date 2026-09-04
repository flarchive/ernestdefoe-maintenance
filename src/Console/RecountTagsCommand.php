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
        }

        /*
         * 🚨 Private and hidden discussions do not count.
         *
         * UpdateTagMetadata's comment says exactly that, though its increment
         * only guards is_private — a hidden discussion is decremented when it is
         * hidden instead. This pair of conditions is what agrees with the stored
         * value on every tag that has NOT drifted, which is what makes it the
         * right rule rather than merely a plausible one.
         */
        $this->db->statement(
            'UPDATE tags t SET t.discussion_count = ('
            .' SELECT COUNT(*) FROM discussion_tag dt'
            .' JOIN discussions d ON d.id = dt.discussion_id'
            .' WHERE dt.tag_id = t.id AND d.hidden_at IS NULL AND d.is_private = 0)'
        );

        $this->info(sprintf('Recounted %d tag(s).', count($drifted)));

        return 0;
    }

    /** @return array<int, object> tags whose stored count disagrees with the data */
    protected function drifted(): array
    {
        return $this->db->select(
            'SELECT t.id, t.name, t.discussion_count AS stored,'
            .' (SELECT COUNT(*) FROM discussion_tag dt'
            .'   JOIN discussions d ON d.id = dt.discussion_id'
            .'   WHERE dt.tag_id = t.id AND d.hidden_at IS NULL AND d.is_private = 0) AS actual'
            .' FROM tags t HAVING stored <> actual'
        );
    }
}
