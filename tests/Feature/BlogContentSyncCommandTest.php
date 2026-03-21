<?php

namespace Tests\Feature;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogContentSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $contentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentRoot = base_path('content/testing-'.Str::uuid());
        File::ensureDirectoryExists($this->contentRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentRoot);

        parent::tearDown();
    }

    public function test_it_imports_multilingual_markdown_posts_and_creates_taxonomies(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $this->writeMarkdown('stripe-vs-paddle/en.md', <<<'MD'
---
title: Stripe vs Paddle for SaaS Billing
slug: stripe-vs-paddle-for-saas-billing
excerpt: English comparison for SaaS founders.
author_email: admin@example.com
category: Billing
tags:
  - Stripe
  - SaaS
status: published
published_at: 2026-03-21 09:00:00
meta_title: Stripe vs Paddle for SaaS Billing
meta_description: Compare Stripe and Paddle for subscriptions, tax, and SaaS growth.
---
# Stripe vs Paddle for SaaS Billing

This is the English version.
MD);

        $this->writeMarkdown('stripe-vs-paddle/de.md', <<<'MD'
---
title: Stripe vs Paddle fuer SaaS Abrechnung
slug: stripe-vs-paddle-saas-abrechnung
excerpt: Deutsche Vergleichsversion fuer SaaS Gruender.
author_email: admin@example.com
category: Billing
tags:
  - Stripe
  - SaaS
status: draft
meta_title: Stripe vs Paddle fuer SaaS Abrechnung
meta_description: Deutsche Version des Abrechnungsvergleichs.
---
# Stripe vs Paddle fuer SaaS Abrechnung

Dies ist die deutsche Version.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments())
            ->assertExitCode(0);

        $englishPost = BlogPost::query()->where('content_source_path', 'stripe-vs-paddle/en.md')->first();
        $germanPost = BlogPost::query()->where('content_source_path', 'stripe-vs-paddle/de.md')->first();

        $this->assertNotNull($englishPost);
        $this->assertNotNull($germanPost);
        $this->assertSame('markdown', $englishPost->content_source);
        $this->assertSame('stripe-vs-paddle', $englishPost->content_source_key);
        $this->assertSame($englishPost->translation_group_uuid, $germanPost->translation_group_uuid);
        $this->assertSame('en', $englishPost->locale);
        $this->assertSame('de', $germanPost->locale);
        $this->assertSame(PostStatus::Published, $englishPost->status);
        $this->assertSame(PostStatus::Draft, $germanPost->status);
        $this->assertNotNull($englishPost->published_at);
        $this->assertStringContainsString('<h1>', (string) $englishPost->body_html);
        $this->assertSame($admin->id, $englishPost->author_id);
        $this->assertSame('billing', $englishPost->category?->slug);
        $this->assertSame(
            ['saas', 'stripe'],
            $englishPost->tags()->orderBy('slug')->pluck('slug')->all()
        );

        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'billing',
            'name' => 'Billing',
        ]);
        $this->assertDatabaseHas('blog_tags', [
            'slug' => 'stripe',
            'name' => 'Stripe',
        ]);
        $this->assertDatabaseHas('blog_tags', [
            'slug' => 'saas',
            'name' => 'SaaS',
        ]);
    }

    public function test_dry_run_previews_import_without_persisting_any_blog_records(): void
    {
        User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $this->writeMarkdown('launch/en.md', <<<'MD'
---
title: Launch Checklist
author_email: admin@example.com
category: Product Updates
tags: [launch, release]
status: published
---
# Launch Checklist

Everything is ready.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments([
            '--dry-run' => true,
        ]))
            ->assertExitCode(0);

        $this->assertCount(0, BlogPost::query()->get());
        $this->assertCount(0, BlogCategory::query()->get());
        $this->assertCount(0, BlogTag::query()->get());
    }

    public function test_it_updates_existing_markdown_managed_posts_without_touching_manual_posts(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $manualPost = BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'author_id' => $admin->id,
            'title' => 'Manual post',
            'slug' => 'manual-post',
            'excerpt' => 'Manual excerpt.',
            'body_markdown' => '# Manual',
            'body_html' => '<h1>Manual</h1>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $this->writeMarkdown('billing-guide/en.md', <<<'MD'
---
title: Billing Guide
slug: billing-guide
author_email: admin@example.com
category: Billing
tags: [stripe]
status: published
---
# Billing Guide

Initial content.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments())
            ->assertExitCode(0);

        $importedPost = BlogPost::query()->where('content_source_path', 'billing-guide/en.md')->first();

        $this->assertNotNull($importedPost);
        $this->assertSame('Billing Guide', $importedPost->title);

        $this->writeMarkdown('billing-guide/en.md', <<<'MD'
---
title: Billing Guide Updated
slug: billing-guide
author_email: admin@example.com
category: Growth
tags: [stripe, conversions]
status: published
meta_description: Updated version.
---
# Billing Guide Updated

Revised content.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments())
            ->assertExitCode(0);

        $importedPost->refresh();
        $manualPost->refresh();

        $this->assertSame('Billing Guide Updated', $importedPost->title);
        $this->assertSame('growth', $importedPost->category?->slug);
        $this->assertSame(
            ['conversions', 'stripe'],
            $importedPost->tags()->orderBy('slug')->pluck('slug')->all()
        );
        $this->assertSame('Manual post', $manualPost->title);
        $this->assertNull($manualPost->content_source);
    }

    public function test_it_can_force_publish_markdown_posts_even_when_the_source_hash_is_unchanged(): void
    {
        User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $this->writeMarkdown('draft-post/en.md', <<<'MD'
---
title: Draft Post
slug: draft-post
author_email: admin@example.com
status: draft
---
# Draft Post

This starts as draft content.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments())
            ->assertExitCode(0);

        $post = BlogPost::query()->where('content_source_path', 'draft-post/en.md')->first();

        $this->assertNotNull($post);
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);

        $this->artisan('blog:sync-content', $this->commandArguments([
            '--publish' => true,
        ]))
            ->assertExitCode(0);

        $post->refresh();
        $publishedAt = $post->published_at;

        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($publishedAt);

        $this->artisan('blog:sync-content', $this->commandArguments([
            '--publish' => true,
        ]))
            ->assertExitCode(0);

        $post->refresh();

        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->published_at->equalTo($publishedAt));
    }

    public function test_it_can_archive_missing_markdown_managed_posts(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $importedPost = BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'content_source' => 'markdown',
            'content_source_key' => 'orphaned-post',
            'content_source_path' => 'orphaned-post/en.md',
            'content_source_hash' => 'hash',
            'content_source_synced_at' => now()->subDay(),
            'author_id' => $admin->id,
            'title' => 'Orphaned markdown post',
            'slug' => 'orphaned-markdown-post',
            'excerpt' => 'Will be archived.',
            'body_markdown' => '# Orphaned',
            'body_html' => '<h1>Orphaned</h1>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('blog:sync-content', $this->commandArguments([
            '--archive-missing' => true,
        ]))
            ->assertExitCode(0);

        $importedPost->refresh();

        $this->assertSame(PostStatus::Archived, $importedPost->status);
        $this->assertNotNull($importedPost->content_source_synced_at);
    }

    public function test_it_fails_when_a_markdown_file_uses_an_unsupported_locale_filename(): void
    {
        User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $this->writeMarkdown('billing-guide/pt.md', <<<'MD'
---
title: Unsupported Locale
author_email: admin@example.com
status: draft
---
# Unsupported Locale

This file should fail validation.
MD);

        $this->artisan('blog:sync-content', $this->commandArguments())
            ->assertExitCode(1);

        $this->assertCount(0, BlogPost::query()->get());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function commandArguments(array $overrides = []): array
    {
        return array_merge([
            '--path' => $this->contentRoot,
        ], $overrides);
    }

    private function writeMarkdown(string $relativePath, string $contents): void
    {
        $absolutePath = $this->contentRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $contents);
    }
}
