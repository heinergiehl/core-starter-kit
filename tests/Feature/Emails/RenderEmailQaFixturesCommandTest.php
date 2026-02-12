<?php

namespace Tests\Feature\Emails;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RenderEmailQaFixturesCommandTest extends TestCase
{
    public function test_it_renders_html_and_text_fixtures_for_email_qa(): void
    {
        $output = storage_path('app/testing/email-qa');

        File::deleteDirectory($output);

        $this->artisan('email:qa:render', ['--output' => $output])
            ->assertExitCode(0);

        $this->assertFileExists($output.DIRECTORY_SEPARATOR.'auth-verify-email.html');
        $this->assertFileExists($output.DIRECTORY_SEPARATOR.'auth-verify-email.txt');
        $this->assertFileExists($output.DIRECTORY_SEPARATOR.'subscription-cancelled.html');
        $this->assertFileExists($output.DIRECTORY_SEPARATOR.'subscription-cancelled.txt');

        $html = File::get($output.DIRECTORY_SEPARATOR.'billing-payment-failed.html');
        $text = File::get($output.DIRECTORY_SEPARATOR.'billing-payment-failed.txt');

        $this->assertStringContainsString('Action Required: Payment Failed', $html);
        $this->assertStringContainsString('Action Required: Payment Failed', $text);
    }
}
