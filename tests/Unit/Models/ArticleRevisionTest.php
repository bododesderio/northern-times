<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ArticleRevision;
use Tests\DatabaseTestCase;

class ArticleRevisionTest extends DatabaseTestCase
{
    public function test_create_revision(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $rev = ArticleRevision::createRevision(
            $article['id'], $user['id'],
            'Test Title', '<p>Some content here for word count.</p>', 'Excerpt'
        );

        $this->assertNotNull($rev);
        $this->assertSame(1, (int)$rev['revision_number']);
        $this->assertSame($article['id'], $rev['article_id']);
        $this->assertSame($user['id'], $rev['user_id']);
        $this->assertGreaterThan(0, (int)$rev['word_count']);
    }

    public function test_revision_numbers_increment(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $rev1 = ArticleRevision::createRevision($article['id'], $user['id'], 'V1', 'Content one', '');
        $rev2 = ArticleRevision::createRevision($article['id'], $user['id'], 'V2', 'Content two', '');
        $rev3 = ArticleRevision::createRevision($article['id'], $user['id'], 'V3', 'Content three', '');

        $this->assertSame(1, (int)$rev1['revision_number']);
        $this->assertSame(2, (int)$rev2['revision_number']);
        $this->assertSame(3, (int)$rev3['revision_number']);
    }

    public function test_for_article(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        ArticleRevision::createRevision($article['id'], $user['id'], 'Rev1', 'Body1', '');
        ArticleRevision::createRevision($article['id'], $user['id'], 'Rev2', 'Body2', '');

        $revisions = ArticleRevision::forArticle($article['id']);
        $this->assertCount(2, $revisions);

        // Should be ordered DESC (latest first)
        $this->assertSame(2, (int)$revisions[0]['revision_number']);
        $this->assertSame(1, (int)$revisions[1]['revision_number']);

        // Should include editor_name from JOIN
        $this->assertArrayHasKey('editor_name', $revisions[0]);
    }

    public function test_count_for_article(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $this->assertSame(0, ArticleRevision::countForArticle($article['id']));

        ArticleRevision::createRevision($article['id'], $user['id'], 'T', 'C', '');
        $this->assertSame(1, ArticleRevision::countForArticle($article['id']));

        ArticleRevision::createRevision($article['id'], $user['id'], 'T2', 'C2', '');
        $this->assertSame(2, ArticleRevision::countForArticle($article['id']));
    }

    public function test_for_article_empty(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $revisions = ArticleRevision::forArticle($article['id']);
        $this->assertIsArray($revisions);
        $this->assertCount(0, $revisions);
    }

    public function test_word_count_accuracy(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $content = '<p>One two three four five six seven eight nine ten.</p>';
        $rev = ArticleRevision::createRevision($article['id'], $user['id'], 'Title', $content, '');

        $this->assertSame(10, (int)$rev['word_count']);
    }

    public function test_null_user_id(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $rev = ArticleRevision::createRevision($article['id'], null, 'System Edit', 'Content', '');
        $this->assertNotNull($rev);
        $this->assertNull($rev['user_id']);
    }

    public function test_find_full(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $rev = ArticleRevision::createRevision($article['id'], $user['id'], 'Full Test', 'Body content', 'Excerpt');
        $full = ArticleRevision::findFull((int)$rev['id']);

        $this->assertNotNull($full);
        $this->assertSame('Full Test', $full['title']);
        $this->assertSame('Body content', $full['content']);
        $this->assertArrayHasKey('editor_name', $full);
    }

    public function test_multiple_articles_independent(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $art1 = $this->createArticle($cat['id'], $user['id']);
        $art2 = $this->createArticle($cat['id'], $user['id']);

        ArticleRevision::createRevision($art1['id'], $user['id'], 'A1R1', 'C', '');
        ArticleRevision::createRevision($art1['id'], $user['id'], 'A1R2', 'C', '');
        ArticleRevision::createRevision($art2['id'], $user['id'], 'A2R1', 'C', '');

        $this->assertSame(2, ArticleRevision::countForArticle($art1['id']));
        $this->assertSame(1, ArticleRevision::countForArticle($art2['id']));
    }
}