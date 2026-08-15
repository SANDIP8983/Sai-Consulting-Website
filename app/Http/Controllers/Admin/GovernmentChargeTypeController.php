<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GovernmentChargeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GovernmentChargeTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.government-charge-types.index', ['types' => GovernmentChargeType::query()->orderBy('sort_order')->orderBy('name_en')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        GovernmentChargeType::query()->create($this->validated($request));

        return back()->with('success', 'Government charge type created.');
    }

    public function update(Request $request, GovernmentChargeType $governmentChargeType): RedirectResponse
    {
        $governmentChargeType->update($this->validated($request, $governmentChargeType));

        return back()->with('success', 'Government charge type updated. Frozen request snapshots are unchanged.');
    }

    private function validated(Request $request, ?GovernmentChargeType $type = null): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:150', Rule::unique('government_charge_types')->ignore($type)],
            'name_gu' => ['nullable', 'string', 'max:150'],
            'default_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
