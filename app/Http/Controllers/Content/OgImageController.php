<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OgImageController
{
    public function __invoke(Request $request): Response
    {
        $title = (string) $request->query('title', config('app.name', 'SaaS Kit'));
        $subtitle = (string) $request->query('subtitle', 'Launch a polished SaaS with teams, billing, and admin tooling.');

        return $this->render($title, $subtitle);
    }

    public function blog(string $slug): Response
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $subtitle = $post->excerpt ?: 'Shipping notes and product updates from the team.';

        return $this->render($post->title, $subtitle);
    }

    private function render(string $title, string $subtitle): Response
    {
        $brandName = config('saas.branding.app_name', config('app.name', 'SaaS Kit'));
        $colors = config('saas.branding.colors', []);
        $fonts = config('saas.branding.fonts', []);

        return response()
            ->view('og.image', [
                'title' => $title,
                'subtitle' => $subtitle,
                'brandName' => $brandName,
                'colors' => $colors,
                'fonts' => $fonts,
            ])
            ->header('Content-Type', 'image/svg+xml');
    }
}
