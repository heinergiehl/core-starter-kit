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
            'status' => \App\Enums\PostStatus::Published,
            'published_at' => now(),
        ]);

        $response = $this->get(route('sitemap.blog'));
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
        $response->assertSee(route('blog.show', ['locale' => $defaultLocale, 'slug' => $post->slug]), false);
    }
}
