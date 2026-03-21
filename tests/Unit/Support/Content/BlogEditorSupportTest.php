<?php

namespace Tests\Unit\Support\Content;

use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Models\User;
use App\Support\Content\BlogEditorSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlogEditorSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BlogEditorSupport::flushTaxonomyLookupCache();
    }

    public function test_it_generates_slugs_from_titles(): void
    {
        $this->assertSame('launch-your-saas-fast', BlogEditorSupport::generateSlug('Launch your SaaS fast'));
    }

    public function test_it_only_auto_updates_slug_when_the_existing_slug_is_still_generated(): void
    {
        $this->assertTrue(BlogEditorSupport::shouldAutoUpdateSlug('', 'Old Title'));
        $this->assertTrue(BlogEditorSupport::shouldAutoUpdateSlug('old-title', 'Old Title'));
        $this->assertFalse(BlogEditorSupport::shouldAutoUpdateSlug('custom-launch-slug', 'Old Title'));
    }

    public function test_it_initializes_blank_slug_editor_state_from_the_current_source_value(): void
    {
        $this->assertSame(
            [
                'slug' => 'saas-kit-alternative',
                'sync' => true,
            ],
            BlogEditorSupport::initializeSlugEditorState('', 'SaaS Kit Alternative'),
        );
    }

    public function test_it_marks_manually_edited_slug_editor_state_as_custom_until_it_matches_the_source_again(): void
    {
        $this->assertSame(
            [
                'slug' => 'custom-billing-guide',
                'sync' => false,
            ],
            BlogEditorSupport::updateSlugEditorState('Custom Billing Guide', 'Stripe Billing Guide'),
        );

        $this->assertSame(
            [
                'slug' => 'stripe-billing-guide',
                'sync' => true,
            ],
            BlogEditorSupport::updateSlugEditorState('Stripe Billing Guide', 'Stripe Billing Guide'),
        );
    }

    public function test_it_describes_slug_behavior_for_synced_and_custom_states(): void
    {
        $this->assertSame(
            'Auto-sync is on. Change the title to update this slug, or edit the slug to make it custom.',
            BlogEditorSupport::describeSlugBehavior('Stripe Billing Guide', true, 'title'),
        );

        $this->assertSame(
            'Custom slug locked in. Reset it to follow the category name again.',
            BlogEditorSupport::describeSlugBehavior('Billing', false, 'category name'),
        );
    }

    public function test_it_renders_syncing_markup_for_synced_slug_status(): void
    {
        $status = BlogEditorSupport::renderSlugSyncState('data.title', true);

        $this->assertInstanceOf(HtmlString::class, $status);
        $this->assertStringContainsString('Auto-sync on', $status->toHtml());
        $this->assertStringContainsString('Syncing...', $status->toHtml());
        $this->assertStringContainsString('wire:target="data.title"', $status->toHtml());
    }

    public function test_it_renders_review_guidance_for_bulk_taxonomy_workflows(): void
    {
        $guidance = BlogEditorSupport::renderTaxonomyDraftReviewGuidance();

        $this->assertInstanceOf(HtmlString::class, $guidance);
        $this->assertStringContainsString('Review tip', $guidance->toHtml());
        $this->assertStringContainsString('Edit the name to keep auto-sync on.', $guidance->toHtml());
    }

    public function test_it_generates_the_next_available_locale_specific_blog_post_slug(): void
    {
        $author = User::factory()->create();

        BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'de',
            'author_id' => $author->id,
            'title' => 'Stripe launch guide',
            'slug' => 'stripe-launch-guide',
            'excerpt' => 'First slug.',
            'body_markdown' => '# Launch',
            'body_html' => '<p>Launch</p>',
            'status' => PostStatus::Draft,
        ]);

        BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'de',
            'author_id' => $author->id,
            'title' => 'Stripe launch guide duplicate',
            'slug' => 'stripe-launch-guide-2',
            'excerpt' => 'Second slug.',
            'body_markdown' => '# Launch duplicate',
            'body_html' => '<p>Launch duplicate</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->assertSame(
            'stripe-launch-guide-3',
            BlogEditorSupport::generateUniqueBlogPostSlug('de', 'stripe-launch-guide', 'Stripe launch guide'),
        );
    }

    public function test_it_ignores_other_locales_when_generating_a_unique_blog_post_slug(): void
    {
        $author = User::factory()->create();

        BlogPost::create([
            'translation_group_uuid' => (string) Str::uuid(),
            'locale' => 'en',
            'author_id' => $author->id,
            'title' => 'Stripe launch guide',
            'slug' => 'stripe-launch-guide',
            'excerpt' => 'English slug.',
            'body_markdown' => '# Launch',
            'body_html' => '<p>Launch</p>',
            'status' => PostStatus::Draft,
        ]);

        $this->assertSame(
            'stripe-launch-guide',
            BlogEditorSupport::generateUniqueBlogPostSlug('de', 'stripe-launch-guide', 'Stripe launch guide'),
        );
    }

    public function test_it_parses_bulk_taxonomy_input_from_commas_newlines_and_semicolons(): void
    {
        $this->assertSame(
            ['Laravel', 'Billing', 'SEO'],
            BlogEditorSupport::parseBulkNames("Laravel, Billing\nSEO; billing")
        );
    }

    public function test_it_prepares_bulk_taxonomy_drafts_with_generated_slugs(): void
    {
        $this->assertSame(
            [
                [
                    'name' => 'Laravel',
                    'slug' => 'laravel',
                ],
                [
                    'name' => 'Product Updates',
                    'slug' => 'product-updates',
                ],
            ],
            BlogEditorSupport::prepareTaxonomyDraftsFromBulkInput("Laravel\nProduct Updates")
        );
    }

    public function test_it_reuses_existing_tags_when_the_name_and_slug_match(): void
    {
        $existingTag = BlogTag::create([
            'name' => 'Filament',
            'slug' => 'filament',
        ]);

        $result = BlogEditorSupport::commitTaxonomyDrafts(
            drafts: [
                [
                    'name' => 'Filament',
                    'slug' => 'filament',
                ],
                [
                    'name' => 'Stripe',
                    'slug' => 'stripe',
                ],
            ],
            modelClass: BlogTag::class,
            singularLabel: 'tag',
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['existing']);
        $this->assertSame([$existingTag->id], BlogTag::query()->where('slug', 'filament')->pluck('id')->all());
        $this->assertCount(2, BlogTag::query()->get());
    }

    public function test_it_rejects_duplicate_slugs_inside_a_bulk_draft(): void
    {
        $this->expectException(ValidationException::class);

        try {
            BlogEditorSupport::validateTaxonomyDrafts(
                drafts: [
                    [
                        'name' => 'Stripe',
                        'slug' => 'payments',
                    ],
                    [
                        'name' => 'Payments',
                        'slug' => 'payments',
                    ],
                ],
                modelClass: BlogTag::class,
                singularLabel: 'tag',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('drafts.0.slug', $exception->errors());
            $this->assertArrayHasKey('drafts.1.slug', $exception->errors());

            throw $exception;
        }
    }

    public function test_it_rejects_existing_slug_conflicts_inside_a_bulk_draft(): void
    {
        BlogTag::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
        ]);

        $this->expectException(ValidationException::class);

        try {
            BlogEditorSupport::validateTaxonomyDrafts(
                drafts: [
                    [
                        'name' => 'Payments',
                        'slug' => 'stripe',
                    ],
                ],
                modelClass: BlogTag::class,
                singularLabel: 'tag',
            );
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This slug already belongs to the existing tag [Stripe].',
                $exception->errors()['drafts.0.slug'][0],
            );

            throw $exception;
        }
    }

    public function test_it_describes_bulk_draft_statuses_for_review_ui(): void
    {
        BlogTag::create([
            'name' => 'Filament',
            'slug' => 'filament',
        ]);

        $drafts = [
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ],
            [
                'name' => 'Filament',
                'slug' => 'filament',
            ],
            [
                'name' => 'Stripe',
                'slug' => 'filament',
            ],
            [
                'name' => '',
                'slug' => '',
            ],
        ];

        $this->assertSame(
            [
                'label' => 'Create new',
                'color' => 'success',
                'icon' => 'heroicon-m-plus-circle',
            ],
            BlogEditorSupport::getTaxonomyDraftStatusMeta('Laravel', 'laravel', $drafts, BlogTag::class),
        );

        $this->assertSame(
            [
                'label' => 'Conflict in list',
                'color' => 'danger',
                'icon' => 'heroicon-m-no-symbol',
            ],
            BlogEditorSupport::getTaxonomyDraftStatusMeta('Filament', 'filament', $drafts, BlogTag::class),
        );

        $this->assertSame(
            [
                'label' => 'Needs attention',
                'color' => 'warning',
                'icon' => 'heroicon-m-pencil-square',
            ],
            BlogEditorSupport::getTaxonomyDraftStatusMeta('', '', $drafts, BlogTag::class),
        );
    }

    public function test_it_summarizes_bulk_draft_statuses_for_review_ui(): void
    {
        BlogTag::create([
            'name' => 'Filament',
            'slug' => 'filament',
        ]);

        $drafts = [
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ],
            [
                'name' => 'Filament',
                'slug' => 'filament',
            ],
            [
                'name' => 'Stripe',
                'slug' => 'stripe',
            ],
        ];

        $this->assertSame(
            [
                'total' => 3,
                'new' => 2,
                'reused' => 1,
                'conflicts' => 0,
                'needs_attention' => 0,
            ],
            BlogEditorSupport::summarizeTaxonomyDraftStatuses($drafts, BlogTag::class),
        );

        $this->assertStringContainsString(
            'Everything in this list is ready to save.',
            BlogEditorSupport::renderTaxonomyDraftSummary($drafts, BlogTag::class, 'tag')->toHtml(),
        );
    }
}
