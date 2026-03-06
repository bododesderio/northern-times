<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tag;
use Tests\DatabaseTestCase;

class TagTest extends DatabaseTestCase
{
    public function test_find_or_create_new(): void
    {
        $tag = Tag::findOrCreate('Kampala-' . bin2hex(random_bytes(3)));
        $this->assertNotEmpty($tag['id']);
        $this->assertNotEmpty($tag['slug']);
    }

    public function test_find_or_create_existing(): void
    {
        $name = 'Existing-' . bin2hex(random_bytes(3));
        $first = Tag::findOrCreate($name);
        $second = Tag::findOrCreate($name);
        $this->assertSame($first['id'], $second['id']);
    }

    public function test_find_by_slug(): void
    {
        $name = 'TestSlug-' . bin2hex(random_bytes(3));
        $tag = Tag::findOrCreate($name);
        $found = Tag::findBy('slug', $tag['slug']);
        $this->assertNotNull($found);
        $this->assertSame($tag['id'], $found['id']);
    }

    public function test_sync_for_article(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        Tag::syncForArticle($article['id'], ['Alpha', 'Beta', 'Gamma']);
        $tags = Tag::forArticle($article['id']);
        $this->assertCount(3, $tags);

        $names = array_column($tags, 'name');
        $this->assertContains('Alpha', $names);
        $this->assertContains('Beta', $names);
        $this->assertContains('Gamma', $names);
    }

    public function test_sync_replaces_tags(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        Tag::syncForArticle($article['id'], ['Old1', 'Old2']);
        $this->assertCount(2, Tag::forArticle($article['id']));

        Tag::syncForArticle($article['id'], ['New1', 'New2', 'New3']);
        $tags = Tag::forArticle($article['id']);
        $this->assertCount(3, $tags);

        $names = array_column($tags, 'name');
        $this->assertNotContains('Old1', $names);
        $this->assertContains('New1', $names);
    }

    public function test_sync_enforces_max_ten(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $tags = [];
        for ($i = 1; $i <= 15; $i++) {
            $tags[] = "Tag{$i}";
        }

        Tag::syncForArticle($article['id'], $tags);
        $result = Tag::forArticle($article['id']);
        $this->assertLessThanOrEqual(10, count($result));
    }

    public function test_sync_empty_clears_tags(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        Tag::syncForArticle($article['id'], ['A', 'B']);
        $this->assertCount(2, Tag::forArticle($article['id']));

        Tag::syncForArticle($article['id'], []);
        $this->assertCount(0, Tag::forArticle($article['id']));
    }

    public function test_for_article_empty(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'draft');

        $tags = Tag::forArticle($article['id']);
        $this->assertIsArray($tags);
        $this->assertCount(0, $tags);
    }

    public function test_search(): void
    {
        Tag::findOrCreate('SearchTestUganda-' . bin2hex(random_bytes(2)));
        $results = Tag::search('SearchTestUganda', 10);
        $this->assertGreaterThanOrEqual(1, count($results));
        $this->assertArrayHasKey('name', $results[0]);
    }

    public function test_search_no_results(): void
    {
        $results = Tag::search('zzzznonexistent999', 10);
        $this->assertCount(0, $results);
    }

    public function test_all_names(): void
    {
        Tag::findOrCreate('AllNamesTest-' . bin2hex(random_bytes(2)));
        $names = Tag::allNames();
        $this->assertIsArray($names);
        $this->assertGreaterThanOrEqual(1, count($names));
        $this->assertArrayHasKey('name', $names[0]);
    }

    public function test_trending(): void
    {
        $trending = Tag::trending(5);
        $this->assertIsArray($trending);
    }

    public function test_articles_paginated(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $tagName = 'PaginateTest-' . bin2hex(random_bytes(3));
        Tag::syncForArticle($article['id'], [$tagName]);
        $tag = Tag::findOrCreate($tagName);

        $result = Tag::articles($tag['slug'], 1, 10);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_auto_tag_from_content(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $content = 'The government of Uganda announced a major infrastructure project in Kampala. Education and health sectors will receive significant funding from parliament.';
        Tag::autoTagFromContent($article['id'], $content, 'Uganda Infrastructure Investment');

        $tags = Tag::forArticle($article['id']);
        $this->assertGreaterThanOrEqual(1, count($tags));
    }
}