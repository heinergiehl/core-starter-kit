<?php

namespace Tests\Unit\Rules;

use App\Rules\MinimumContrast;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MinimumContrastTest extends TestCase
{
    public function test_it_accepts_values_that_meet_minimum_ratio(): void
    {
        $validator = Validator::make(
            ['color' => '#4F46E5'],
            ['color' => [new MinimumContrast('#FFFFFF', 4.5)]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_it_rejects_values_below_minimum_ratio(): void
    {
        $validator = Validator::make(
            ['color' => '#F8FAFC'],
            ['color' => [new MinimumContrast('#FFFFFF', 4.5)]]
        );

        $this->assertTrue($validator->fails());
    }
}
