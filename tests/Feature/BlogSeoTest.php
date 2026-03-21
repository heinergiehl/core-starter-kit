<?php

namespace Tests\Feature;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_search_works_on_the_testing_database_driver(): void
    {
        $post = $this->createPublishedPost([
            'title' => 'Searchable billing update',
            'slug' => 'searchable-billing-update',
            'excerpt' => 'Search should find this published post.',
        ]);

        $response = $this->get(route('blog.index', [
            'locale' => 'en',
            'search' => 'billing',
        ]));

        $response->assertOk();
        $response->assertSeeText($post->title);
    }

    public function test_filtered_blog_archives_are_noindex_and_canonicalize_to_the_root_archive(): void
    {
        $category = BlogCategory::create([
            'name' => 'Billing',
            'slug' => 'billing',
        ]);

        $this->createPublishedPost([
            'category_id' => $category->id,
            'title' => 'Billing changes',
            'slug' => 'billing-changes',
        ]);

        $response = $this->get(route('blog.index', [
            'locale' => 'en',
            'category' => $category->slug,
        ]));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,follow">', false);
        $response->assertSee(
            '<link rel="canonical" href="'.route('blog.index', ['locale' => 'en']).'">',
            false
        );
    }

    public function test_paginated_blog_archive_uses_a_self_referencing_canonical(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->createPublishedPost([
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('blog.index', [
            'locale' => 'en',
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertSee(
            '<link rel="canonical" href="'.route('blog.index', ['locale' => 'en']).'?page=2">',
            false
        );
    }

    public function test_blog_post_includes_article_metadata_and_structured_data(): void
    {
        $category = BlogCategory::create([
            'name' => 'Product Updates',
            'slug' => 'product-updates',
        ]);

        $tag = BlogTag::create([
            'name' => 'Launch',
            'slug' => 'launch',
        ]);

        $post = $this->createPublishedPost([
            'category_id' => $category->id,
            'title' => 'Launch checklist',
            'slug' => 'launch-checklist',
            'excerpt' => 'Everything shipped for the launch.',
            'body_html' => '<h2>Launch</h2><p>Checklist content for the launch day.</p>',
        ]);

        $post->tags()->attach($tag);

        $response = $this->get(route('blog.show', [
            'locale' => 'en',
            'slug' => $post->slug,
        ]));

        $response->assertOk();
        $response->assertSee('property="article:published_time"', false);
        $response->assertSee('"@type": "BlogPosting"', false);
        $response->assertSee('"@type": "BreadcrumbList"', false);
        $response->assertSee(route('blog.show', ['locale' => 'en', 'slug' => $post->slug]), false);
    }

    public function test_blog_post_only_outputs_hreflang_links_for_existing_published_translations(): void
    {
        $groupUuid = (string) Str::uuid();

        $englishPost = $this->createPublishedPost([
            'translation_group_uuid' => $groupUuid,
            'locale' => 'en',
            'title' => 'Launch notes',
            'slug' => 'launch-notes',
        ]);

        $this->createPublishedPost([
            'translation_group_uuid' => $groupUuid,
            'locale' => 'de',
            'title' => 'Startnotizen',
            'slug' => 'startnotizen',
        ]);

        BlogPost::create([
            'translation_group_uuid' => $groupUuid,
            'locale' => 'fr',
            'author_id' => User::factory()->create()->id,
            'title' => 'Notes de lancement',
            'slug' => 'notes-de-lancement',
            'excerpt' => 'French translation still in draft.',
            'body_markdown' => '# Launch',
            'status' => PostStatus::Draft,
        ]);

        $response = $this->get(route('blog.show', [
            'locale' => 'en',
            'slug' => $englishPost->slug,
        ]));

        $response->assertOk();
        $response->assertSee(
            '<link rel="alternate" hreflang="en" href="'.route('blog.show', ['locale' => 'en', 'slug' => 'launch-notes']).'">',
            false
        );
        $response->assertSee(
            '<link rel="alternate" hreflang="de" href="'.route('blog.show', ['locale' => 'de', 'slug' => 'startnotizen']).'">',
            false
        );
        $response->assertDontSee(
            route('blog.show', ['locale' => 'fr', 'slug' => 'notes-de-lancement']),
            false
        );
    }

    public function test_blog_post_route_returns_not_found_when_the_locale_variant_does_not_exist(): void
    {
        $post = $this->createPublishedPost([
            'locale' => 'en',
            'title' => 'English only launch notes',
            'slug' => 'english-only-launch-notes',
        ]);

        $response = $this->get(route('blog.show', [
            'locale' => 'de',
            'slug' => $post->slug,
        ]));

        $response->assertNotFound();
    }

    public function test_robots_route_uses_the_dynamic_sitemap_url(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /', false);
        $response->assertSee('Sitemap: '.route('sitemap'), false);
    }

    private function createPublishedPost(array $overrides = []): BlogPost
    {
        $authorId = $overrides['author_id'] ?? User::factory()->create()->id;
        unset($overrides['author_id']);

        return BlogPost::create(array_merge([
            'author_id' => $authorId,
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'title' => 'Default post',
            'slug' => 'default-post',
            'excerpt' => 'Default excerpt',
            'body_markdown' => '# Default',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ], $overrides));
    }
}
