<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRequestStaffRequest;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Services\RequestAssignmentService;
use Illuminate\Http\RedirectResponse;

class RequestAssignmentController extends Controller
{
    public function __construct(private readonly RequestAssignmentService $assignments) {}

    public function __invoke(AssignRequestStaffRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $assignee = User::query()->findOrFail($request->integer('assigned_user_id'));
        $this->assignments->assign($customerRequest, $assignee, $request->user());

        return back()->with('success', $customerRequest->assigned_user_id ? 'Request reassigned successfully.' : 'Request assigned successfully.');
    }
}
