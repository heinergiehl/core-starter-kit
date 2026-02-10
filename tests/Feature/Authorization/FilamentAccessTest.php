<?php

namespace Tests\Feature\Authorization;

use App\Domain\Settings\Models\BrandSetting;
use App\Domain\Billing\Models\Subscription;
use App\Enums\PermissionName;
use App\Filament\Admin\Pages\ManageBranding;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_roles_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles/create')->assertForbidden();
    }

    public function test_non_admin_cannot_access_admin_permissions_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/permissions')->assertForbidden();
        $this->actingAs($user)->get('/admin/permissions/create')->assertForbidden();
        $this->actingAs($user)->get('/admin/stats')->assertForbidden();
    }

    public function test_admin_can_access_admin_roles_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/roles/create')->assertOk();
    }

    public function test_admin_can_access_admin_permissions_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/permissions')->assertOk();
        $this->actingAs($admin)->get('/admin/permissions/create')->assertOk();
        $this->actingAs($admin)->get('/admin/stats')->assertOk();
    }

    public function test_user_with_access_admin_panel_permission_can_access_admin_dashboard(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create([
            'name' => PermissionName::AccessAdminPanel->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        $user = User::factory()->create(['is_admin' => false]);
        $user->givePermissionTo(PermissionName::AccessAdminPanel->value);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    }

    public function test_admin_dashboard_uses_curated_operator_widgets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Quick Actions')
            ->assertDontSeeText('Documentation');
    }

    public function test_admin_can_access_subscriptions_page_when_status_is_enum_backed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Subscription::factory()->create();

        $this->actingAs($admin)->get('/admin/subscriptions')->assertOk();
    }

    public function test_admin_can_access_settings_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/manage-settings')->assertOk();
        $this->actingAs($admin)->get('/admin/manage-branding')->assertOk();
        $this->actingAs($admin)->get('/admin/manage-email-settings')->assertOk();

        $expectedTemplate = config('template.active', 'default');
        $this->assertNotNull(BrandSetting::query()->find(BrandSetting::GLOBAL_ID));
        $this->assertSame($expectedTemplate, BrandSetting::query()->find(BrandSetting::GLOBAL_ID)?->template);
    }

    public function test_manage_branding_hydrates_default_template_when_missing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $expectedTemplate = config('template.active', 'default');

        DB::table('brand_settings')->insert([
            'id' => BrandSetting::GLOBAL_ID,
            'app_name' => null,
            'logo_path' => null,
            'color_primary' => null,
            'color_secondary' => null,
            'color_accent' => null,
            'color_bg' => null,
            'color_fg' => null,
            'template' => '',
            'invoice_name' => null,
            'invoice_email' => null,
            'invoice_address' => null,
            'invoice_tax_id' => null,
            'invoice_footer' => null,
            'email_primary_color' => null,
            'email_secondary_color' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/manage-branding')->assertOk();

        $this->assertSame($expectedTemplate, BrandSetting::query()->find(BrandSetting::GLOBAL_ID)?->template);
    }

    public function test_manage_branding_save_persists_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageBranding::class)
            ->set('data.app_name', 'ShipSolid QA')
            ->set('data.template', 'default')
            ->set('data.invoice_email', 'billing@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('brand_settings', [
            'id' => BrandSetting::GLOBAL_ID,
            'app_name' => 'ShipSolid QA',
            'template' => 'default',
            'invoice_email' => 'billing@example.com',
        ]);
    }
}
