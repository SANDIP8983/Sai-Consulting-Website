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

    private const INITIAL_PASSWORD = 'DeploymentOnly!42';

    public function test_admin_seeder_is_idempotent(): void
    {
        config(['admin.mobile' => '9876543205', 'admin.password' => self::INITIAL_PASSWORD, 'admin.email' => null]);
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->sole();
        $originalHash = $admin->password;

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertNull($admin->fresh()->email);
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertTrue(Hash::check(self::INITIAL_PASSWORD, $admin->fresh()->password));
    }

    public function test_fresh_admin_seed_requires_an_explicit_password(): void
    {
        config(['admin.mobile' => '9876543206', 'admin.password' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD is required');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_explicit_configuration_creates_a_hashed_super_admin_without_email(): void
    {
        config([
            'admin.name' => 'Production Administrator',
            'admin.username' => 'admin',
            'admin.mobile' => '9876543206',
            'admin.email' => null,
            'admin.password' => self::INITIAL_PASSWORD,
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('Production Administrator', $admin->name);
        $this->assertSame('admin', $admin->username);
        $this->assertSame('9876543206', $admin->mobile);
        $this->assertNull($admin->email);
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNotSame(self::INITIAL_PASSWORD, $admin->password);
        $this->assertTrue(Hash::check(self::INITIAL_PASSWORD, $admin->password));
    }

    public function test_conflicting_super_admin_fails_without_creating_or_modifying_users(): void
    {
        $existing = User::factory()->create([
            'username' => 'existing-owner',
            'role' => 'super_admin',
            'mobile' => '9876543207',
        ]);
        config([
            'admin.username' => 'admin',
            'admin.mobile' => '9876543206',
            'admin.password' => self::INITIAL_PASSWORD,
        ]);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('Expected conflicting Super Admin bootstrap to fail safely.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflicting Super Admin', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'username' => 'existing-owner', 'role' => 'super_admin']);
    }
}
