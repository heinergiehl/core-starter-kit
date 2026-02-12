<?php

namespace Tests\Unit\Support\Color;

use App\Support\Color\Contrast;
use Tests\TestCase;

class ContrastTest extends TestCase
{
    public function test_normalize_hex_supports_short_and_missing_hash_values(): void
    {
        $this->assertSame('#AABBCC', Contrast::normalizeHex('#abc'));
        $this->assertSame('#A155F1', Contrast::normalizeHex('A155F1'));
        $this->assertNull(Contrast::normalizeHex('invalid'));
    }

    public function test_contrast_ratio_matches_expected_ordering(): void
    {
        $highContrast = Contrast::contrastRatio('#FFFFFF', '#000000');
        $lowContrast = Contrast::contrastRatio('#FFFFFF', '#A855F7');

        $this->assertNotNull($highContrast);
        $this->assertNotNull($lowContrast);
        $this->assertGreaterThan($lowContrast, $highContrast);
    }

    public function test_best_text_color_picks_more_readable_variant(): void
    {
        $this->assertSame('#FFFFFF', Contrast::bestTextColor('#111827'));
        $this->assertSame('#0F172A', Contrast::bestTextColor('#F1F5F9'));
    }
}
