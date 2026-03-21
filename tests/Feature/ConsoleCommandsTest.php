<?php

namespace Tests\Feature;

use App\Console\Commands\SetupWizard;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    public function test_console_commands_expose_supported_admin_and_billing_workflows(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $output = Artisan::output();
        $wizard = new \ReflectionClass(SetupWizard::class);
        $syncCommand = $wizard->getReflectionConstant('CATALOG_SYNC_COMMAND')?->getValue();

        $this->assertIsString($syncCommand);
        $this->assertSame('billing:publish-catalog', $syncCommand);
        $this->assertStringContainsString('app:create-admin', $output);
        $this->assertStringContainsString('blog:sync-content', $output);
        $this->assertStringContainsString('billing:publish-catalog', $output);
        $this->assertStringNotContainsString('app:create-admin-command', $output);
    }
}
