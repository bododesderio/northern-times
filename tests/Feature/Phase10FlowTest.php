<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\FrontendController;
use App\Models\Tag;
use App\Models\ArticleRevision;
use App\Models\User;
use Tests\DatabaseTestCase;

class Phase10FlowTest extends DatabaseTestCase
{
    private FrontendController $ctrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctrl = new FrontendController();
    }

    // ── Tag Page ─────────────────────────────────────────────

    public function test_tag_page_returns_200(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $tagName = 'TagPage-' . bin2hex(random_bytes(3));
        Tag::syncForArticle($article['id'], [$tagName]);
        $tag = Tag::findOrCreate($tagName);

        $response = $this->ctrl->tag($tag['slug']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($tagName, $response->getContent());
    }

    public function test_tag_page_404_for_nonexistent(): void
    {
        $response = $this->ctrl->tag('nonexistent-tag-' . bin2hex(random_bytes(4)));
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_tag_page_shows_articles(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $art1 = $this->createArticle($cat['id'], $user['id'], 'published');
        $art2 = $this->createArticle($cat['id'], $user['id'], 'published');

        $tagName = 'MultiArticle-' . bin2hex(random_bytes(3));
        Tag::syncForArticle($art1['id'], [$tagName]);
        Tag::syncForArticle($art2['id'], [$tagName]);

        $tag = Tag::findOrCreate($tagName);
        $response = $this->ctrl->tag($tag['slug']);
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($art1['title'], $content);
        $this->assertStringContainsString($art2['title'], $content);
    }

    // ── Author Page ──────────────────────────────────────────

    public function test_author_page_returns_200(): void
    {
        $user = $this->createUser();
        $cat = $this->createCategory();
        $this->createArticle($cat['id'], $user['id'], 'published');

        $response = $this->ctrl->author($user['username']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_author_page_404_for_nonexistent(): void
    {
        $response = $this->ctrl->author('nonexistent_user_' . bin2hex(random_bytes(4)));
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_author_page_shows_articles(): void
    {
        $user = $this->createUser();
        $cat = $this->createCategory();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $response = $this->ctrl->author($user['username']);
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($article['title'], $content);
    }

    public function test_author_page_excludes_drafts(): void
    {
        $user = $this->createUser();
        $cat = $this->createCategory();
        $published = $this->createArticle($cat['id'], $user['id'], 'published');
        $draft = $this->createArticle($cat['id'], $user['id'], 'draft');

        $response = $this->ctrl->author($user['username']);
        $content = $response->getContent();

        $this->assertStringContainsString($published['title'], $content);
        $this->assertStringNotContainsString($draft['title'], $content);
    }

    // ── Tag Search API ───────────────────────────────────────

    public function test_tag_search_api_returns_json(): void
    {
        $name = 'ApiSearch-' . bin2hex(random_bytes(3));
        Tag::findOrCreate($name);

        // Simulate the query
        $_GET['q'] = 'ApiSearch';
        $response = $this->ctrl->tagSearchApi();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertSame($name, $data[0]['name']);

        unset($_GET['q']);
    }

    public function test_tag_search_api_empty_query(): void
    {
        $_GET['q'] = '';
        $response = $this->ctrl->tagSearchApi();
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
        unset($_GET['q']);
    }

    // ── Tag + Article Integration ────────────────────────────

    public function test_tag_sync_on_article_roundtrip(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        // Add tags
        Tag::syncForArticle($article['id'], ['Uganda', 'Politics', 'Health']);
        $tags = Tag::forArticle($article['id']);
        $this->assertCount(3, $tags);

        // Update tags (remove one, add one)
        Tag::syncForArticle($article['id'], ['Uganda', 'Health', 'Economy']);
        $tags = Tag::forArticle($article['id']);
        $names = array_column($tags, 'name');
        $this->assertCount(3, $tags);
        $this->assertContains('Economy', $names);
        $this->assertNotContains('Politics', $names);
    }

    // ── Revision + Article Integration ───────────────────────

    public function test_revision_created_for_article(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id']);

        $rev = ArticleRevision::createRevision(
            $article['id'], $user['id'],
            $article['title'], $article['content'], $article['excerpt']
        );
        $this->assertNotNull($rev);

        $revisions = ArticleRevision::forArticle($article['id']);
        $this->assertCount(1, $revisions);
        $this->assertSame(1, (int)$revisions[0]['revision_number']);
        $this->assertSame($user['username'], $revisions[0]['editor_name']);
    }
}