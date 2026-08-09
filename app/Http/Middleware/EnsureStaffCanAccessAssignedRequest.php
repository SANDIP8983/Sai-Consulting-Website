<?php

namespace App\Http\Middleware;

use App\Models\CustomerRequest;
use App\Services\RequestAssignmentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffCanAccessAssignedRequest
{
    public function __construct(private readonly RequestAssignmentService $assignments) {}

    public function handle(Request $request, Closure $next): Response
    {
        $customerRequest = $request->route('customerRequest');
        if ($customerRequest instanceof CustomerRequest && $request->user()) {
            $this->assignments->assertStaffCanAccess($customerRequest, $request->user());
        }

        return $next($request);
    }
}
