<?php

namespace Tests\Feature\Authorization;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Settings\Models\BrandSetting;
use App\Enums\OrderStatus;
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

    public function test_admin_can_access_orders_page_when_status_is_enum_backed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();

        Order::query()->create([
            'user_id' => $customer->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_test_order_1',
            'plan_key' => 'starter',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [],
        ]);

        $this->actingAs($admin)->get('/admin/orders')->assertOk();
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
            ->set('data.color_primary', '#4f46e5')
            ->set('data.color_secondary', '#a855f7')
            ->set('data.color_accent', '#be185d')
            ->set('data.email_primary_color', '#1D4ED8')
            ->set('data.email_secondary_color', '#112233')
            ->set('data.invoice_email', 'billing@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('brand_settings', [
            'id' => BrandSetting::GLOBAL_ID,
            'app_name' => 'ShipSolid QA',
            'template' => 'default',
            'color_primary' => '#4F46E5',
            'color_secondary' => '#A855F7',
            'color_accent' => '#BE185D',
            'email_primary_color' => '#1D4ED8',
            'email_secondary_color' => '#112233',
            'invoice_email' => 'billing@example.com',
        ]);
    }

    public function test_manage_branding_rejects_low_contrast_email_colors(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageBranding::class)
            ->set('data.template', 'default')
            ->set('data.email_primary_color', '#F8FAFC')
            ->set('data.email_secondary_color', '#F1F5F9')
            ->call('save')
            ->assertHasErrors(['data.email_primary_color', 'data.email_secondary_color']);
    }

    public function test_manage_branding_can_reset_interface_colors_to_template_defaults(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageBranding::class)
            ->set('data.template', 'default')
            ->set('data.color_primary', '#191A14')
            ->set('data.color_secondary', '#002BFF')
            ->set('data.color_accent', '#FF1111')
            ->call('resetInterfaceColors')
            ->assertSet('data.color_primary', null)
            ->assertSet('data.color_secondary', null)
            ->assertSet('data.color_accent', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('brand_settings', [
            'id' => BrandSetting::GLOBAL_ID,
            'color_primary' => null,
            'color_secondary' => null,
            'color_accent' => null,
        ]);
    }

    public function test_manage_branding_can_reset_email_colors_to_defaults(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageBranding::class)
            ->set('data.template', 'default')
            ->set('data.email_primary_color', '#1D4ED8')
            ->set('data.email_secondary_color', '#112233')
            ->call('resetEmailColors')
            ->assertSet('data.email_primary_color', null)
            ->assertSet('data.email_secondary_color', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('brand_settings', [
            'id' => BrandSetting::GLOBAL_ID,
            'email_primary_color' => null,
            'email_secondary_color' => null,
        ]);
    }
}
