<?php

namespace Tests\Unit\Support\Content;

use App\Support\Content\BlogEditorSupport;
use PHPUnit\Framework\TestCase;

class BlogEditorSupportTest extends TestCase
{
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

    public function test_it_parses_bulk_taxonomy_input_from_commas_newlines_and_semicolons(): void
    {
        $this->assertSame(
            ['Laravel', 'Billing', 'SEO'],
            BlogEditorSupport::parseBulkNames("Laravel, Billing\nSEO; billing")
        );
    }
}
