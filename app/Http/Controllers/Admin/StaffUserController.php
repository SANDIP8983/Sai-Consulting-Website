<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetStaffPasswordRequest;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateStaffUserRequest;
use App\Models\User;
use App\Services\StaffAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StaffUserController extends Controller
{
    public function __construct(private readonly StaffAccountService $accounts) {}

    public function index(): View
    {
        $users = User::query()->orderBy('name')->paginate(20);
        $actor = request()->user();

        return view('admin.users.index', [
            'users' => $users,
            'deletableUserIds' => $users->getCollection()
                ->filter(fn (User $user): bool => $this->accounts->canDelete($actor, $user))
                ->pluck('id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreStaffUserRequest $request): RedirectResponse
    {
        $this->accounts->create($request->validated());

        return to_route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateStaffUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->validated());

        return to_route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function resetPassword(ResetStaffPasswordRequest $request, User $user): RedirectResponse
    {
        $this->accounts->resetPassword($user, $request->validated('password'));

        return back()->with('success', 'Password reset successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->accounts->delete(request()->user(), $user);

        return to_route('admin.users.index')->with('success', 'User permanently deleted.');
    }
}
