<?php

namespace ErnestDefoe\Maintenance\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

class RecountTagsTest extends ConsoleTestCase
{
    private function withTags(): void
    {
        $this->extension('flarum-tags', 'ernestdefoe-maintenance');

        $this->prepareDatabase([
            Tag::class => [
                // Drifted under: two countable discussions, stored as 0.
                ['id' => 1, 'name' => 'Under', 'slug' => 'under', 'position' => 0, 'discussion_count' => 0],
                // Drifted over: one countable discussion, stored as 7.
                ['id' => 2, 'name' => 'Over', 'slug' => 'over', 'position' => 1, 'discussion_count' => 7],
                // Correct already: hidden and private discussions don't count.
                ['id' => 3, 'name' => 'Right', 'slug' => 'right', 'position' => 2, 'discussion_count' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'One', 'created_at' => Carbon::now(), 'user_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Two', 'created_at' => Carbon::now(), 'user_id' => 1, 'comment_count' => 1],
                ['id' => 3, 'title' => 'Hidden', 'created_at' => Carbon::now(), 'user_id' => 1, 'comment_count' => 1, 'hidden_at' => Carbon::now()],
                ['id' => 4, 'title' => 'Private', 'created_at' => Carbon::now(), 'user_id' => 1, 'comment_count' => 1, 'is_private' => true],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 1],
                ['discussion_id' => 4, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
                ['discussion_id' => 2, 'tag_id' => 3],
                ['discussion_id' => 3, 'tag_id' => 3],
                ['discussion_id' => 4, 'tag_id' => 3],
            ],
        ]);
    }

    private function counts(): array
    {
        return $this->database()->table('tags')->orderBy('id')->pluck('discussion_count', 'id')->map(fn ($n) => (int) $n)->all();
    }

    #[Test]
    public function drifted_counts_are_put_back_and_hidden_and_private_discussions_do_not_count()
    {
        $this->withTags();

        $output = $this->runCommand(['command' => 'tags:recount']);

        $this->assertSame([1 => 2, 2 => 1, 3 => 1], $this->counts());
        $this->assertStringContainsString('Recounted 2 tag(s).', $output);
        $this->assertStringNotContainsString('Right', $output, 'A tag that had not drifted is not reported');
    }

    #[Test]
    public function a_second_run_finds_nothing_to_do()
    {
        $this->withTags();

        $this->runCommand(['command' => 'tags:recount']);
        $output = $this->runCommand(['command' => 'tags:recount']);

        $this->assertSame('All tag counts are already correct.', $output);
        $this->assertSame([1 => 2, 2 => 1, 3 => 1], $this->counts());
    }
}
