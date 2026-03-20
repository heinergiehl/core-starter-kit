<?php

namespace App\Http\Controllers\Content;

use Illuminate\Http\Response;

class RobotsController
{
    public function __invoke(): Response
    {
        $allowIndexing = app()->environment('production');

        $lines = [
            'User-agent: *',
            $allowIndexing ? 'Disallow:' : 'Disallow: /',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode(PHP_EOL, $lines).PHP_EOL, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
