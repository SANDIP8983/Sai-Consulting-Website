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

    public function test_admin_seeder_is_idempotent_and_preserves_existing_credentials(): void
    {
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->sole();
        $originalHash = $admin->password;

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(config('admin.email'), $admin->fresh()->email);
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertTrue(Hash::check(config('admin.password'), $admin->fresh()->password));
    }

    public function test_admin_seeder_renames_the_legacy_account_without_changing_its_password(): void
    {
        $legacy = User::factory()->create([
            'name' => 'Test User',
            'email' => config('admin.legacy_email'),
        ]);
        $originalHash = $legacy->password;

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('Admin', $admin->name);
        $this->assertSame(config('admin.email'), $admin->email);
        $this->assertSame($originalHash, $admin->password);
        $this->assertDatabaseMissing('users', ['email' => config('admin.legacy_email')]);
    }
}
