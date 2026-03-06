<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Article;
use Tests\DatabaseTestCase;

class ArticleTest extends DatabaseTestCase
{
    private array $cat;
    private array $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat  = $this->createCategory();
        $this->user = $this->createUser();
    }

    public function test_store_and_find(): void
    {
        $article = Article::store([
            'title'         => 'Test Title',
            'slug'          => 'test-title-' . bin2hex(random_bytes(4)),
            'content'       => '<p>Content here.</p>',
            'excerpt'       => 'Short excerpt.',
            'author_id'     => $this->user['id'],
            'category_id'   => $this->cat['id'],
            'status'        => 'draft',
            'display_author'=> 'John Doe',
        ]);

        $this->assertNotNull($article);
        $this->assertSame('Test Title', $article['title']);
        $this->assertSame('draft', $article['status']);

        $found = Article::find($article['id']);
        $this->assertNotNull($found);
        $this->assertSame($article['id'], $found['id']);
    }

    public function test_update_article(): void
    {
        $article = $this->createArticle($this->cat['id'], $this->user['id']);
        Article::update($article['id'], ['title' => 'Updated Title']);

        $found = Article::find($article['id']);
        $this->assertSame('Updated Title', $found['title']);
    }

    public function test_delete_article(): void
    {
        $article = $this->createArticle($this->cat['id'], $this->user['id']);
        $deleted = Article::delete($article['id']);

        $this->assertTrue($deleted);
        $this->assertNull(Article::find($article['id']));
    }

    public function test_status_workflow_draft_to_pending(): void
    {
        $article = $this->createArticle($this->cat['id'], $this->user['id'], 'draft');
        Article::submitForReview($article['id']);

        $found = Article::find($article['id']);
        $this->assertSame('pending_review', $found['status']);
    }

    public function test_approve_sets_published(): void
    {
        $article = $this->createArticle($this->cat['id'], $this->user['id'], 'pending_review');
        Article::approveArticle($article['id'], $this->user['id'], 'Looks good');

        $found = Article::find($article['id']);
        $this->assertSame('published', $found['status']);
        $this->assertSame('Looks good', $found['review_notes']);
        $this->assertNotNull($found['reviewed_at']);
    }

    public function test_reject_sets_draft(): void
    {
        $article = $this->createArticle($this->cat['id'], $this->user['id'], 'pending_review');
        Article::rejectArticle($article['id'], $this->user['id'], 'Needs work');

        $found = Article::find($article['id']);
        $this->assertSame('draft', $found['status']);
        $this->assertSame('Needs work', $found['review_notes']);
    }

    public function test_pending_review_listing(): void
    {
        $this->createArticle($this->cat['id'], $this->user['id'], 'pending_review');
        $this->createArticle($this->cat['id'], $this->user['id'], 'draft');
        $this->createArticle($this->cat['id'], $this->user['id'], 'pending_review');

        $count = Article::pendingCount();
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_count_by_conditions(): void
    {
        $this->createArticle($this->cat['id'], $this->user['id'], 'published');
        $this->createArticle($this->cat['id'], $this->user['id'], 'draft');

        $published = Article::count(['status' => 'published']);
        $drafts    = Article::count(['status' => 'draft']);

        $this->assertGreaterThanOrEqual(1, $published);
        $this->assertGreaterThanOrEqual(1, $drafts);
    }

    public function test_valid_statuses_constant(): void
    {
        $expected = ['draft', 'pending_review', 'published', 'archived', 'scheduled'];
        $this->assertSame($expected, Article::VALID_STATUSES);
    }
}