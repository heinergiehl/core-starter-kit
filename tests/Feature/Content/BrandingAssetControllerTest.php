<?php

namespace Tests\Feature\Content;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class BrandingAssetControllerTest extends TestCase
{
    public function test_it_serves_existing_public_branding_assets(): void
    {
        $expectedPath = realpath(public_path('branding/shipsolid-s-mark.svg'));

        $response = $this->get('/branding/shipsolid-s-mark.svg');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame($expectedPath, $response->baseResponse->getFile()->getRealPath());
        $this->assertStringContainsString('image/svg+xml', (string) $response->headers->get('content-type'));
    }

    public function test_it_falls_back_to_default_brand_mark_when_asset_is_missing(): void
    {
        $fallbackPath = realpath(public_path('branding/shipsolid-s-mark.svg'));

        $response = $this->get('/branding/does-not-exist.png');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame($fallbackPath, $response->baseResponse->getFile()->getRealPath());
        $this->assertStringContainsString('image/svg+xml', (string) $response->headers->get('content-type'));
    }

    public function test_it_rejects_path_traversal_attempts(): void
    {
        $this->get('/branding/../.env')->assertNotFound();
    }
}
