<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommonRequiredDocument;
use App\Support\PublicDocumentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommonRequiredDocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CommonRequiredDocument::query()->create([
            ...$data,
            'code' => Str::slug($data['code']),
            'normalized_name' => Str::slug($data['code']),
            'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
            'max_upload_size_kb' => 10240,
            'is_common' => true,
        ]);

        return back()->with('success', 'Central document created successfully.');
    }

    public function edit(CommonRequiredDocument $masterDocument)
    {
        return view('admin.required-documents.master-edit', compact('masterDocument'));
    }

    public function update(Request $request, CommonRequiredDocument $masterDocument): RedirectResponse
    {
        $data = $this->validated($request, $masterDocument);
        $masterDocument->update([...$data, 'code' => Str::slug($data['code']), 'normalized_name' => Str::slug($data['code'])]);

        return to_route('admin.required-documents.index')->with('success', 'Central document updated successfully.');
    }

    private function validated(Request $request, ?CommonRequiredDocument $document = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('common_required_documents', 'code')->ignore($document)],
            'name_gu' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150', function ($attribute, $value, $fail): void {
                if (! PublicDocumentPolicy::isSafe((string) $value)) {
                    $fail('Identity/KYC documents cannot be added to the public document master.');
                }
            }],
            'display_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
