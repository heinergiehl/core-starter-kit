<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use App\Domain\Settings\Services\BrandingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OgImageController
{
    public function __invoke(Request $request): Response
    {
        $title = (string) $request->query('title', config('app.name', 'SaaS Kit'));
        $subtitle = (string) $request->query('subtitle', 'Launch a polished SaaS with billing, auth, and admin tooling.');

        return $this->render($title, $subtitle);
    }

    public function blog(string $slug): Response
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->firstOrFail();

        $subtitle = $post->excerpt ?: 'Shipping notes and product updates from the product team.';

        return $this->render($post->title, $subtitle);
    }

    private function render(string $title, string $subtitle): Response
    {
        $brandName = app(BrandingService::class)->appName();
        $fonts = config('saas.branding.fonts', []);

        return response()
            ->view('og.image', [
                'title' => $title,
                'subtitle' => $subtitle,
                'brandName' => $brandName,
                'fonts' => $fonts,
            ])
            ->header('Content-Type', 'image/svg+xml');
    }
}
