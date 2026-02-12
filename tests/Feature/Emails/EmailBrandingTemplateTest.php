<?php

namespace Tests\Feature\Emails;

use App\Domain\Settings\Models\BrandSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailBrandingTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_layout_applies_primary_and_secondary_brand_colors(): void
    {
        BrandSetting::query()->create([
            'email_primary_color' => '#111111',
            'email_secondary_color' => '#22AA66',
        ]);

        Cache::forget('branding.global');

        $html = view('emails.auth.verify-email', [
            'url' => 'https://example.com/verify',
        ])->render();

        $this->assertStringContainsString('background-color: #111111;', $html);
        $this->assertStringContainsString('border: 1px solid #22AA66;', $html);
        $this->assertStringContainsString('.email-body a {', $html);
        $this->assertStringContainsString('style="color: #22AA66; text-decoration: underline;">Visit', $html);
    }

    public function test_payment_failed_template_uses_semantic_danger_button_class(): void
    {
        $html = view('emails.billing.payment-failed', [
            'user' => (object) ['name' => 'Customer'],
            'planName' => 'Starter',
        ])->render();

        $this->assertStringContainsString('class="btn btn-danger"', $html);
        $this->assertStringNotContainsString('style="background-color: #ef4444;"', $html);
        $this->assertStringContainsString('.btn-danger { background-color: #DC2626;', $html);
    }
}
