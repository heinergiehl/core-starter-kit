<?php

namespace App\Http\Controllers\Content;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BrandingAssetController
{
    public function __invoke(string $path): BinaryFileResponse|StreamedResponse
    {
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        try {
            $publicPath = public_path("branding/{$path}");
            if (is_file($publicPath)) {
                return response()->file($publicPath);
            }

            $storagePath = "branding/{$path}";
            if (Storage::disk('public')->exists($storagePath)) {
                return Storage::disk('public')->response($storagePath);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to serve branding asset.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }

        $fallback = public_path('branding/shipsolid-s-mark.svg');
        if (is_file($fallback)) {
            return response()->file($fallback);
        }

        abort(Response::HTTP_NOT_FOUND);
    }
}
