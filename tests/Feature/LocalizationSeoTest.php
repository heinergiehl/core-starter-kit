<?php

namespace Tests\Feature;

use App\Domain\Content\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_home_redirects_to_default_locale_regardless_of_browser_language(): void
    {
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        $response = $this->withHeaders([
            'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
        ])->get('/');

        $response->assertStatus(301);
        $response->assertRedirect(route('home', ['locale' => $defaultLocale]));
    }

    public function test_legacy_marketing_redirect_preserves_non_locale_query_parameters(): void
    {
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        $response = $this->get('/pricing?lang=de&status=open');

        $response->assertStatus(301);
        $this->assertSame(
            route('pricing', ['locale' => $defaultLocale]).'?status=open',
            $response->headers->get('Location')
        );
    }

    public function test_localized_marketing_page_contains_hreflang_and_localized_canonical_links(): void
    {
        $response = $this->get(route('features', ['locale' => 'de']));

        $response->assertOk();
        $response->assertSee(
            '<link rel="canonical" href="'.route('features', ['locale' => 'de']).'">',
            false
        );

        foreach (array_keys(config('saas.locales.supported', ['en' => 'English'])) as $locale) {
            $response->assertSee(
                '<link rel="alternate" hreflang="'.$locale.'" href="'.route('features', ['locale' => $locale]).'">',
                false
            );
        }

        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));
        $response->assertSee(
            'hreflang="x-default"',
            false
        );
        $response->assertSee(
            'href="'.route('features', ['locale' => $defaultLocale]).'"',
            false
        );
    }

    public function test_sitemap_contains_multilingual_alternate_links(): void
    {
        $response = $this->get(route('sitemap.marketing'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
        $response->assertSee('xmlns:xhtml="http://www.w3.org/1999/xhtml"', false);

        foreach (array_keys(config('saas.locales.supported', ['en' => 'English'])) as $locale) {
            $response->assertSee(
                '<xhtml:link rel="alternate" hreflang="'.$locale.'" href="'.route('home', ['locale' => $locale]).'" />',
                false
            );
        }
    }

    public function test_sitemap_index_lists_split_sitemaps(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<sitemapindex', false);
        $response->assertSee(route('sitemap.marketing'), false);
        $response->assertSee(route('sitemap.blog'), false);
    }

    public function test_localized_docs_show_route_renders_expected_content(): void
    {
        $response = $this->get(route('docs.show', ['locale' => 'en', 'page' => 'billing']));

        $response->assertOk();
        $response->assertSeeText('Billing (Stripe, Paddle)');
    }

    public function test_localized_solution_show_route_renders_known_solution(): void
    {
        $response = $this->get(route('solutions.show', [
            'locale' => 'en',
            'slug' => 'laravel-stripe-paddle-billing-starter',
        ]));

        $response->assertOk();
        $response->assertSeeText('Laravel SaaS billing with Stripe and Paddle in one production flow');
    }

    public function test_localized_blog_show_route_renders_published_post(): void
    {
        $author = User::factory()->create();

        $post = BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Locale routing post',
            'slug' => 'locale-routing-post',
            'excerpt' => 'Localized blog route should resolve.',
            'body_markdown' => '# Routing',
            'status' => \App\Enums\PostStatus::Published,
            'published_at' => now(),
        ]);

        $response = $this->get(route('blog.show', [
            'locale' => 'en',
            'slug' => $post->slug,
        ]));

        $response->assertOk();
        $response->assertSeeText($post->title);
    }

    public function test_locale_switch_redirect_uses_route_matching_for_legacy_urls(): void
    {
        $response = $this->post(route('locale.update'), [
            'locale' => 'de',
            'redirect' => 'http://localhost:8000/blog/senior-routing-check?lang=fr&utm_source=nav#top',
        ]);

        $response->assertStatus(302);
        $this->assertSame(
            route('blog.show', ['locale' => 'de', 'slug' => 'senior-routing-check']).'?utm_source=nav#top',
            $response->headers->get('Location')
        );
    }

    public function test_locale_switch_redirect_preserves_non_localized_internal_paths(): void
    {
        $response = $this->post(route('locale.update'), [
            'locale' => 'fr',
            'redirect' => '/dashboard?tab=billing',
        ]);

        $response->assertStatus(302);
        $this->assertSame(
            url('/dashboard').'?tab=billing',
            $response->headers->get('Location')
        );
    }

    public function test_marketing_navigation_home_link_is_locale_aware(): void
    {
        $response = $this->get(route('features', ['locale' => 'de']));

        $response->assertOk();
        $response->assertSee(
            'href="'.route('home', ['locale' => 'de']).'"',
            false
        );
    }

    public function test_localized_responses_include_content_language_header(): void
    {
        $response = $this->get(route('features', ['locale' => 'de']));

        $response->assertOk();
        $response->assertHeader('Content-Language', 'de');
    }

    public function test_locale_switch_rejects_external_redirect_targets(): void
    {
        $response = $this->post(route('locale.update'), [
            'locale' => 'es',
            'redirect' => 'https://evil.example/phishing',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('home', ['locale' => 'es']));
    }
}
