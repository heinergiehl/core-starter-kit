<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_index_page_is_available(): void
    {
        $this->get(route('docs.index'))
            ->assertOk()
            ->assertSeeText('Documentation')
            ->assertSee('/docs/billing', false);
    }

    public function test_specific_doc_page_renders_markdown_content(): void
    {
        $this->get(route('docs.show', ['page' => 'billing']))
            ->assertOk()
            ->assertSeeText('Billing (Stripe, Paddle)');
    }

    public function test_unknown_doc_page_returns_not_found(): void
    {
        $this->get(route('docs.show', ['page' => 'does-not-exist']))
            ->assertNotFound();
    }
}
