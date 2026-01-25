<?php

namespace Tests\Feature;

use App\Domain\Content\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RssFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_feed_returns_latest_posts(): void
    {
        $author = User::factory()->create();

        $post = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Architecture update',
            'slug' => 'architecture-update',
            'excerpt' => 'Domain-first improvements.',
            'body_markdown' => '## Notes',
            'status' => \App\Enums\PostStatus::Published,
            'published_at' => now(),
        ]);

        $response = $this->get('/rss.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee('<rss', false);
        $response->assertSee($post->title, false);
    }
}
