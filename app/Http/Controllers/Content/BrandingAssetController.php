<?php

namespace App\Http\Controllers\Content;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingAssetController
{
    public function __invoke(string $path): BinaryFileResponse|StreamedResponse
    {
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $publicPath = public_path("branding/{$path}");
        if (is_file($publicPath)) {
            return response()->file($publicPath);
        }

        $storagePath = "branding/{$path}";
        if (Storage::disk('public')->exists($storagePath)) {
            return Storage::disk('public')->response($storagePath);
        }

        abort(Response::HTTP_NOT_FOUND);
    }
}

