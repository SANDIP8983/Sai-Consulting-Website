<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_is_idempotent(): void
    {
        config(['admin.mobile' => '9876543205']);
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->sole();
        $originalHash = $admin->password;

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(config('admin.email'), $admin->fresh()->email);
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertTrue(Hash::check(config('admin.password'), $admin->fresh()->password));
    }

    public function test_admin_seeder_promotes_legacy_account_without_replacing_contact_or_password(): void
    {
        $legacy = User::factory()->create([
            'name' => 'Test User',
            'email' => config('admin.legacy_email'),
        ]);
        $originalEmail = $legacy->email;
        $originalMobile = $legacy->mobile;
        $originalHash = $legacy->password;
        config(['admin.mobile' => '9876543206']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('Admin', $admin->name);
        $this->assertSame('admin', $admin->username);
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertSame($originalEmail, $admin->email);
        $this->assertSame($originalMobile, $admin->mobile);
        $this->assertSame($originalHash, $admin->password);
    }
}
