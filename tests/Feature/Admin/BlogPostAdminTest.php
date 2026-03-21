<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Filament\Admin\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Admin\Resources\BlogPostResource\Pages\EditBlogPost;
use App\Models\User;
use App\Support\Content\BlogEditorSupport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BlogPostAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BlogEditorSupport::flushTaxonomyLookupCache();
    }

    public function test_admin_can_open_blog_post_create_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/blog-posts/create')
            ->assertOk()
            ->assertSeeText('Create Blog Post')
            ->assertSeeText('Summary')
            ->assertSeeText('Locale')
            ->assertSeeText('SEO Title')
            ->assertSeeText('Auto-sync on')
            ->assertSeeText('Syncing...');
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

    public function test_admin_can_bulk_create_and_select_tags_from_the_blog_post_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $existingTag = BlogTag::create([
            'name' => 'Filament',
            'slug' => 'filament',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        $component = Livewire::test(CreateBlogPost::class)
            ->assertFormComponentActionExists('tags', 'pasteTags')
            ->mountFormComponentAction('tags', 'pasteTags')
            ->setFormComponentActionData([
                'names' => 'Laravel SaaS Starter Kit, Filament, Laravel SaaS Starter Kit, Stripe',
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertMountedActionModalSee([
                'Everything in this list is ready to save.',
                'Create new',
                'Reuse existing',
            ])
            ->assertFormComponentActionDataSet([
                'drafts.0.name' => 'Laravel SaaS Starter Kit',
                'drafts.0.slug' => 'laravel-saas-starter-kit',
                'drafts.1.name' => 'Filament',
                'drafts.1.slug' => 'filament',
                'drafts.2.name' => 'Stripe',
                'drafts.2.slug' => 'stripe',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $tags = BlogTag::query()
            ->orderBy('name')
            ->get();

        $this->assertCount(3, $tags);
        $this->assertTrue($tags->pluck('id')->contains($existingTag->id));
        $this->assertSame(
            $tags->pluck('id')->sort()->values()->all(),
            collect($component->get('data.tags'))
                ->map(static fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all()
        );
    }

    public function test_admin_can_bulk_create_a_single_category_from_the_blog_post_form_and_auto_select_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        $component = Livewire::test(CreateBlogPost::class)
            ->assertFormComponentActionExists('category_id', 'pasteCategories')
            ->mountFormComponentAction('category_id', 'pasteCategories')
            ->setFormComponentActionData([
                'names' => 'Product Updates',
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertFormComponentActionDataSet([
                'drafts.0.name' => 'Product Updates',
                'drafts.0.slug' => 'product-updates',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $category = BlogCategory::query()->first();

        $this->assertNotNull($category);
        $this->assertSame('product-updates', $category->slug);
        $this->assertSame($category->id, (int) $component->get('data.category_id'));
    }

    public function test_blog_post_slug_can_be_reset_back_to_the_generated_title_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CreateBlogPost::class)
            ->set('data.title', 'Stripe vs Paddle Billing Guide')
            ->assertSet('data.slug', 'stripe-vs-paddle-billing-guide')
            ->assertSet('data.slug_sync_enabled', true)
            ->set('data.slug', 'billing-guide')
            ->assertSet('data.slug_sync_enabled', false)
            ->set('data.title', 'Stripe vs Paddle Billing Deep Dive')
            ->assertSet('data.slug', 'billing-guide')
            ->assertFormComponentActionExists('slug', 'resetTitleSlugSyncEnabledSlug')
            ->callFormComponentAction('slug', 'resetTitleSlugSyncEnabledSlug')
            ->assertSet('data.slug', 'stripe-vs-paddle-billing-deep-dive')
            ->assertSet('data.slug_sync_enabled', true)
            ->set('data.title', 'Stripe vs Paddle Billing Checklist')
            ->assertSet('data.slug', 'stripe-vs-paddle-billing-checklist');
    }

    public function test_admin_can_create_a_translation_draft_from_an_existing_blog_post(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tag = BlogTag::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
        ]);

        $post = BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'author_id' => $admin->id,
            'title' => 'Stripe launch guide',
            'slug' => 'stripe-launch-guide',
            'excerpt' => 'English launch guide.',
            'body_markdown' => '# Launch',
            'body_html' => '<h2>Launch</h2><p>English content.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
        $post->tags()->attach($tag);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->assertActionVisible('createTranslation')
            ->mountAction('createTranslation')
            ->assertMountedActionModalSee(['Create translation draft', 'Locale'])
            ->fillForm([
                'locale' => 'de',
            ])
            ->callMountedAction();

        $translation = BlogPost::query()
            ->where('translation_group_uuid', $post->translation_group_uuid)
            ->where('locale', 'de')
            ->first();

        $this->assertNotNull($translation);
        $this->assertSame('stripe-launch-guide', $translation->slug);
        $this->assertSame('Stripe launch guide', $translation->title);
        $this->assertSame(PostStatus::Draft, $translation->status);
        $this->assertNull($translation->published_at);
        $this->assertSame([$tag->id], $translation->tags()->pluck('blog_tags.id')->all());
    }

    public function test_translation_draft_creation_adjusts_the_slug_when_the_target_locale_slug_is_taken(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'de',
            'author_id' => $admin->id,
            'title' => 'Existing German guide',
            'slug' => 'stripe-launch-guide',
            'excerpt' => 'Existing German slug.',
            'body_markdown' => '# Existing',
            'body_html' => '<p>Existing German content.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $post = BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'author_id' => $admin->id,
            'title' => 'Stripe launch guide',
            'slug' => 'stripe-launch-guide',
            'excerpt' => 'English launch guide.',
            'body_markdown' => '# Launch',
            'body_html' => '<h2>Launch</h2><p>English content.</p>',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->mountAction('createTranslation')
            ->fillForm([
                'locale' => 'de',
            ])
            ->callMountedAction();

        $translation = BlogPost::query()
            ->where('translation_group_uuid', $post->translation_group_uuid)
            ->where('locale', 'de')
            ->first();

        $this->assertNotNull($translation);
        $this->assertSame('stripe-launch-guide-2', $translation->slug);
        $this->assertSame(PostStatus::Draft, $translation->status);
    }

    public function test_admin_can_bulk_create_multiple_categories_without_forcing_a_primary_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        $component = Livewire::test(CreateBlogPost::class)
            ->mountFormComponentAction('category_id', 'pasteCategories')
            ->setFormComponentActionData([
                'names' => "Billing\nGrowth",
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertCount(2, BlogCategory::query()->get());
        $this->assertNull($component->get('data.category_id'));
    }

    public function test_bulk_tag_creation_stops_when_a_reviewed_slug_conflicts_with_an_existing_tag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        BlogTag::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CreateBlogPost::class)
            ->mountFormComponentAction('tags', 'pasteTags')
            ->setFormComponentActionData([
                'names' => 'Payments',
            ])
            ->goToNextWizardStep()
            ->setFormComponentActionData([
                'drafts' => [
                    [
                        'name' => 'Payments',
                        'slug' => 'stripe',
                    ],
                ],
            ])
            ->callMountedAction()
            ->assertHasFormComponentActionErrors(['drafts.0.slug'])
            ->assertFormComponentActionMounted('tags', 'pasteTags');

        $this->assertCount(1, BlogTag::query()->get());
    }
}
