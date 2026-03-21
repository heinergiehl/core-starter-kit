<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogTag;
use App\Filament\Admin\Resources\BlogCategoryResource\Pages\CreateBlogCategory;
use App\Filament\Admin\Resources\BlogCategoryResource\Pages\EditBlogCategory;
use App\Filament\Admin\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use App\Filament\Admin\Resources\BlogTagResource\Pages\ListBlogTags;
use App\Models\User;
use App\Support\Content\BlogEditorSupport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogTaxonomyAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BlogEditorSupport::flushTaxonomyLookupCache();
    }

    public function test_admin_can_review_and_bulk_create_tags_from_the_tags_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        BlogTag::create([
            'name' => 'Filament',
            'slug' => 'filament',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(ListBlogTags::class)
            ->assertTableActionExists('bulkCreate')
            ->mountTableAction('bulkCreate')
            ->setTableActionData([
                'names' => "Filament\nStripe",
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertMountedActionModalSee([
                'Slug Mode',
                'Review tip',
                'Edit the name to keep auto-sync on.',
                'Syncing...',
            ])
            ->assertTableActionDataSet([
                'drafts.0.slug' => 'filament',
                'drafts.0.slug_sync_enabled' => true,
                'drafts.1.slug' => 'stripe',
                'drafts.1.slug_sync_enabled' => true,
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertCount(2, BlogTag::query()->get());
    }

    public function test_category_slug_stays_synced_until_the_admin_customizes_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(CreateBlogCategory::class)
            ->set('data.name', 'Laravel SaaS Starter')
            ->assertSet('data.slug', 'laravel-saas-starter')
            ->assertSet('data.slug_sync_enabled', true)
            ->set('data.slug', 'starter-kit')
            ->assertSet('data.slug', 'starter-kit')
            ->assertSet('data.slug_sync_enabled', false)
            ->set('data.name', 'Laravel SaaS Starter Pro')
            ->assertSet('data.slug', 'starter-kit')
            ->set('data.slug', 'laravel-saas-starter-pro')
            ->assertSet('data.slug_sync_enabled', true)
            ->set('data.name', 'Laravel SaaS Starter Max')
            ->assertSet('data.slug', 'laravel-saas-starter-max');
    }

    public function test_edit_category_page_recovers_a_blank_slug_from_the_existing_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = BlogCategory::create([
            'name' => 'SaaSkit Alternative',
            'slug' => '',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(EditBlogCategory::class, ['record' => $category->getRouteKey()])
            ->assertSet('data.slug', 'saaskit-alternative')
            ->assertSet('data.slug_sync_enabled', true);
    }

    public function test_admin_can_review_and_bulk_create_categories_from_the_categories_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(ListBlogCategories::class)
            ->assertTableActionExists('bulkCreate')
            ->mountTableAction('bulkCreate')
            ->setTableActionData([
                'names' => "Billing\nProduct Updates",
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertMountedActionModalSee([
                'Slug Mode',
                'Review tip',
                'Edit the name to keep auto-sync on.',
                'Syncing...',
            ])
            ->assertTableActionDataSet([
                'drafts.0.slug' => 'billing',
                'drafts.0.slug_sync_enabled' => true,
                'drafts.1.slug' => 'product-updates',
                'drafts.1.slug_sync_enabled' => true,
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertCount(2, BlogCategory::query()->get());
    }
}
