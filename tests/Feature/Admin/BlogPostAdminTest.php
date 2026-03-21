<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Filament\Admin\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogPostAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_blog_post_create_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/blog-posts/create')
            ->assertOk()
            ->assertSeeText('Create Blog Post')
            ->assertSeeText('Summary')
            ->assertSeeText('SEO Title');
    }

    public function test_admin_can_create_a_published_blog_post_without_setting_a_publish_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = BlogCategory::create([
            'name' => 'Billing',
            'slug' => 'billing',
        ]);
        $tag = BlogTag::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CreateBlogPost::class)
            ->set('data.title', 'Stripe vs Paddle for SaaS billing')
            ->set('data.slug', 'stripe-vs-paddle-for-saas-billing')
            ->set('data.excerpt', 'Compare the trade-offs between Stripe and Paddle.')
            ->set('data.body_html', '<h2>Overview</h2><p>Billing comparison.</p>')
            ->set('data.category_id', $category->id)
            ->set('data.tags', [$tag->id])
            ->set('data.author_id', $admin->id)
            ->set('data.status', PostStatus::Published->value)
            ->set('data.meta_title', '')
            ->set('data.meta_description', '')
            ->call('create')
            ->assertHasNoErrors();

        $post = BlogPost::query()->where('slug', 'stripe-vs-paddle-for-saas-billing')->first();

        $this->assertNotNull($post);
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertSame($category->id, $post->category_id);
    }
}
