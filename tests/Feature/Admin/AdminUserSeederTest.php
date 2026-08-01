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
}
