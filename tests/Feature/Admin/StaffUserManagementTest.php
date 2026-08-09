<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass!42';

    public function test_existing_super_admin_and_username_only_staff_can_log_in(): void
    {
        config(['admin.mobile' => '9876543201', 'admin.password' => self::PASSWORD]);
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->sole();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->is_active);

        $this->post(route('login.store'), ['login' => $admin->username, 'password' => config('admin.password')])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->post(route('admin.logout'));

        $staff = User::factory()->create(['username' => 'staff.one', 'email' => null, 'role' => 'staff', 'password' => self::PASSWORD]);
        $this->post(route('login.store'), ['login' => 'STAFF.ONE', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_email_and_legacy_email_field_cannot_be_used_to_log_in(): void
    {
        $user = User::factory()->create([
            'username' => 'username.only',
            'email' => 'username-only@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->post(route('login.store'), ['login' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post(route('login.store'), ['login' => $user->username, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_fresh_admin_seed_requires_an_explicit_valid_mobile(): void
    {
        foreach ([null, '', '5876543210', '987654321', '98765432100', '98A6543210'] as $mobile) {
            config(['admin.mobile' => $mobile]);

            try {
                $this->seed(AdminUserSeeder::class);
                $this->fail('The admin seeder accepted a missing or invalid ADMIN_MOBILE value.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('ADMIN_MOBILE is required', $exception->getMessage());
            }

            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_fresh_admin_seed_with_valid_mobile_succeeds_and_email_remains_optional(): void
    {
        config(['admin.mobile' => '9876543201', 'admin.password' => self::PASSWORD, 'admin.email' => null]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'username' => 'admin',
            'email' => null,
            'mobile' => '9876543201',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $super = User::query()->where('username', 'admin')->firstOrFail();
        $this->actingAs($super)->post(route('admin.users.store'), $this->payload([
            'username' => 'no.email',
            'email' => null,
            'mobile' => '9876543202',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'no.email', 'email' => null]);
    }

    public function test_reseeding_preserves_existing_admin_mobile_email_and_password_hash(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'owner@example.test',
            'mobile' => '9876543203',
            'role' => 'super_admin',
            'is_active' => true,
            'password' => self::PASSWORD,
        ]);
        $originalEmail = $admin->email;
        $originalMobile = $admin->mobile;
        $originalPasswordHash = $admin->password;
        config([
            'admin.email' => 'different@example.test',
            'admin.mobile' => '9876543204',
            'admin.password' => 'DifferentPass!98',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin->refresh();
        $this->assertSame('admin', $admin->username);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertSame($originalEmail, $admin->email);
        $this->assertSame($originalMobile, $admin->mobile);
        $this->assertSame($originalPasswordHash, $admin->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
    }

    public function test_inactive_invalid_and_throttled_logins_are_rejected(): void
    {
        $inactive = User::factory()->create(['username' => 'inactive', 'is_active' => false, 'password' => self::PASSWORD]);
        $this->post(route('login.store'), ['login' => $inactive->username, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $active = User::factory()->create(['username' => 'rate.test', 'password' => self::PASSWORD]);
        $this->post(route('login.store'), ['login' => $active->username, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');
        foreach (range(1, 5) as $attempt) {
            $response = $this->post(route('login.store'), ['login' => $active->username, 'password' => 'wrong-password']);
        }
        $response->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_super_admin_can_create_staff_and_admin_without_email(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs($super)->post(route('admin.users.store'), $this->payload([
                'username' => strtoupper($role).'.USER', 'email' => null, 'mobile' => $role === 'staff' ? '9876543210' : '9876543211', 'role' => $role,
            ]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('users', ['username' => $role.'.user', 'email' => null, 'role' => $role, 'is_active' => true]);
        }
        $this->assertTrue(Hash::check(self::PASSWORD, User::query()->where('username', 'staff.user')->value('password')));
    }

    public function test_user_creation_requires_a_unique_valid_ten_digit_indian_mobile(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['mobile' => '9876543200']);

        foreach ([null, '', '987654321', '98765432100', '98A6543210', '5876543210', '9876543200'] as $mobile) {
            $this->actingAs($super)->post(route('admin.users.store'), $this->payload([
                'username' => 'mobile'.md5((string) $mobile), 'email' => null, 'mobile' => $mobile,
            ]))->assertSessionHasErrors('mobile');
        }

        $this->actingAs($super)->post(route('admin.users.store'), $this->payload([
            'username' => 'valid.mobile', 'email' => null, 'mobile' => ' 98765 43210 ',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'valid.mobile', 'mobile' => '9876543210', 'email' => null]);
    }

    public function test_edit_mobile_uniqueness_ignores_current_user_but_rejects_another_user(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $first = User::factory()->create(['role' => 'staff', 'mobile' => '9876543212']);
        $second = User::factory()->create(['role' => 'staff', 'mobile' => '9876543213']);

        $this->actingAs($super)->put(route('admin.users.update', $first), $this->payload([
            'username' => $first->username, 'email' => $first->email, 'mobile' => $first->mobile,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($super)->put(route('admin.users.update', $first), $this->payload([
            'username' => $first->username, 'email' => $first->email, 'mobile' => $second->mobile,
        ]))->assertSessionHasErrors('mobile');
    }

    public function test_user_creation_validates_unique_identity_and_strong_password(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['username' => 'duplicate', 'email' => 'duplicate@example.com']);

        $this->actingAs($super)->post(route('admin.users.store'), $this->payload([
            'username' => 'DUPLICATE', 'email' => 'duplicate@example.com', 'password' => 'weak', 'password_confirmation' => 'weak',
        ]))->assertSessionHasErrors(['username', 'email', 'password']);
    }

    public function test_super_admin_can_reset_an_email_null_users_password(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['role' => 'staff', 'email' => null, 'password' => self::PASSWORD]);
        $newPassword = 'ResetSecure!88';

        $this->actingAs($super)->put(route('admin.users.password', $staff), [
            'password' => $newPassword, 'password_confirmation' => $newPassword,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($newPassword, $staff->fresh()->password));
        $this->assertFalse(Hash::check(self::PASSWORD, $staff->fresh()->password));
    }

    public function test_staff_and_admin_cannot_manage_users_or_security_configuration(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.settings.company-branding'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.services.index'))->assertForbidden();
        }
    }

    public function test_admin_cannot_modify_super_admin_and_staff_cannot_self_promote(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->put(route('admin.users.update', $super), $this->payload(['role' => 'staff']))->assertForbidden();
        $this->actingAs($staff)->put(route('admin.profile.update'), [
            'name' => $staff->name, 'username' => $staff->username, 'email' => null, 'mobile' => $staff->mobile, 'role' => 'super_admin',
        ])->assertSessionHasNoErrors();
        $this->assertSame('staff', $staff->fresh()->role);
    }

    public function test_last_active_super_admin_cannot_be_demoted_or_deactivated(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        foreach ([['role' => 'admin', 'is_active' => 1], ['role' => 'super_admin', 'is_active' => 0]] as $change) {
            $this->actingAs($super)->put(route('admin.users.update', $super), $this->payload($change))
                ->assertSessionHasErrors('role');
            $this->assertTrue($super->fresh()->isSuperAdmin());
            $this->assertTrue($super->fresh()->is_active);
        }
    }

    public function test_user_can_update_profile_but_not_role(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'email' => null]);
        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => 'Updated Staff', 'username' => 'UPDATED.STAFF', 'email' => '', 'mobile' => '98765 43210', 'role' => 'super_admin',
        ])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Updated Staff', $user->name);
        $this->assertSame('updated.staff', $user->username);
        $this->assertNull($user->email);
        $this->assertSame('staff', $user->role);
    }

    public function test_profile_mobile_is_required_and_unique(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'mobile' => '9876543214']);
        $other = User::factory()->create(['mobile' => '9876543215']);
        $profile = ['name' => $user->name, 'username' => $user->username, 'email' => null];

        $this->actingAs($user)->put(route('admin.profile.update'), [...$profile, 'mobile' => ''])
            ->assertSessionHasErrors('mobile');
        $this->actingAs($user)->put(route('admin.profile.update'), [...$profile, 'mobile' => $other->mobile])
            ->assertSessionHasErrors('mobile');
        $this->assertSame('9876543214', $user->fresh()->mobile);
    }

    public function test_password_change_requires_correct_current_password_and_rehashes(): void
    {
        $user = User::factory()->create(['username' => 'password.user', 'password' => self::PASSWORD]);
        $newPassword = 'NewSecure!567';

        $this->actingAs($user)->put(route('admin.profile.password'), [
            'current_password' => 'wrong', 'password' => $newPassword, 'password_confirmation' => $newPassword,
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));

        $this->actingAs($user)->put(route('admin.profile.password'), [
            'current_password' => self::PASSWORD, 'password' => $newPassword, 'password_confirmation' => $newPassword,
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));

        $this->post(route('admin.logout'));
        $this->post(route('login.store'), ['login' => $user->username, 'password' => self::PASSWORD])->assertSessionHasErrors('login');
        $this->post(route('login.store'), ['login' => $user->username, 'password' => $newPassword])->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_has_server_side_operational_permissions_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertDontSee('Users / Staff');
        $this->actingAs($staff)->get(route('admin.requests.index'))->assertOk();
        $this->assertTrue($staff->can('processing.manage'));
        $this->assertTrue($staff->can('dispatch.manage'));
        $this->assertFalse($staff->can('billing.manage'));
        $this->assertFalse($staff->can('users.manage'));
    }

    public function test_super_admin_can_delete_an_unreferenced_staff_user_and_login_stops(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['role' => 'staff', 'username' => 'delete.me', 'password' => self::PASSWORD]);
        $staffId = $staff->id;

        $this->actingAs($super)->delete(route('admin.users.destroy', $staff))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $staffId]);
        $this->post(route('admin.logout'));
        $this->post(route('login.store'), ['login' => 'delete.me', 'password' => self::PASSWORD])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_staff_and_admin_cannot_issue_direct_user_delete_requests(): void
    {
        $target = User::factory()->create(['role' => 'staff']);
        foreach (['staff', 'admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $super))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_user_cannot_delete_self_or_the_last_active_super_admin(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($super)->delete(route('admin.users.destroy', $super))->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $super->id, 'role' => 'super_admin', 'is_active' => true]);
        $this->assertSame(1, User::query()->where('role', 'super_admin')->where('is_active', true)->count());
    }

    public function test_referenced_user_cannot_be_deleted_but_can_be_deactivated_without_history_loss(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['role' => 'staff', 'password' => self::PASSWORD]);
        $service = Service::query()->create(['name_en' => 'History Service', 'name_gu' => 'History Service', 'slug' => 'history-service', 'is_active' => true, 'sort_order' => 1]);
        $customerRequest = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/990001', 'service_id' => $service->id,
            'name' => 'History Customer', 'mobile' => '9999999999', 'status' => 'under_review',
        ]);
        $history = $customerRequest->statusHistory()->create([
            'from_status' => 'received', 'to_status' => 'under_review', 'changed_by' => $staff->id,
        ]);
        $beforeRequests = CustomerRequest::query()->count();
        $beforeHistories = $customerRequest->statusHistory()->count();

        $this->actingAs($super)->delete(route('admin.users.destroy', $staff))->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('request_status_histories', ['id' => $history->id, 'changed_by' => $staff->id]);

        $this->actingAs($super)->put(route('admin.users.update', $staff), [
            'name' => $staff->name, 'username' => $staff->username, 'email' => $staff->email,
            'mobile' => $staff->mobile, 'role' => 'staff', 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame($beforeRequests, CustomerRequest::query()->count());
        $this->assertSame($beforeHistories, $customerRequest->statusHistory()->count());
        $this->assertDatabaseHas('request_status_histories', ['id' => $history->id, 'changed_by' => $staff->id]);

        $this->post(route('admin.logout'));
        $this->post(route('login.store'), ['login' => $staff->username, 'password' => self::PASSWORD])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_assignment_references_prevent_staff_hard_delete(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $service = Service::query()->create(['name_en' => 'Assignment History Service', 'name_gu' => 'Assignment History Service', 'slug' => 'assignment-history-service', 'is_active' => true]);
        CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/990002', 'service_id' => $service->id, 'name' => 'Assignment Customer', 'mobile' => '9999999999',
            'status' => 'awaiting_staff_assignment', 'assigned_user_id' => $staff->id, 'assigned_by' => $super->id, 'assigned_at' => now(),
        ]);

        $this->actingAs($super)->delete(route('admin.users.destroy', $staff))->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Staff User', 'username' => 'staff.user', 'email' => 'staff@example.com',
            'mobile' => '9876543299', 'role' => 'staff', 'is_active' => 1,
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            ...$overrides,
        ];
    }
}
