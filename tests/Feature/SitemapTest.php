<?php

namespace Tests\Feature;

use App\Domain\Content\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_published_blog_posts(): void
    {
        $author = User::factory()->create();

        $post = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Release notes',
            'slug' => 'release-notes',
            'excerpt' => 'What shipped this week.',
            'body_markdown' => '# Update',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(route('blog.show', $post->slug), false);
    }
}
