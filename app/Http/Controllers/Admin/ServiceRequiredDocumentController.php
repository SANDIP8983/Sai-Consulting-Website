<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterServiceRequiredDocumentsRequest;
use App\Http\Requests\Admin\ReorderServiceRequiredDocumentsRequest;
use App\Http\Requests\Admin\StoreServiceRequiredDocumentRequest;
use App\Http\Requests\Admin\UpdateServiceRequiredDocumentRequest;
use App\Models\CommonRequiredDocument;
use App\Models\Service;
use App\Models\ServiceRequiredDocument;
use App\Services\ServiceRequiredDocumentManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceRequiredDocumentController extends Controller
{
    public function __construct(private readonly ServiceRequiredDocumentManagementService $documents) {}

    public function index(FilterServiceRequiredDocumentsRequest $request): View
    {
        return view('admin.required-documents.index', [
            'documents' => $this->documents->paginate($request->validated()),
            'services' => Service::query()->orderBy('name_en')->get(['id', 'name_en', 'name_gu']),
            'masters' => CommonRequiredDocument::query()->orderBy('display_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.required-documents.create', ['services' => Service::query()->orderBy('name_en')->get()]);
    }

    public function store(StoreServiceRequiredDocumentRequest $request): RedirectResponse
    {
        $this->documents->create($request->validated());

        return to_route('admin.required-documents.index')->with('success', 'Required document created successfully.');
    }

    public function edit(ServiceRequiredDocument $requiredDocument): View
    {
        return view('admin.required-documents.edit', [
            'document' => $requiredDocument,
            'services' => Service::query()->orderBy('name_en')->get(),
        ]);
    }

    public function update(UpdateServiceRequiredDocumentRequest $request, ServiceRequiredDocument $requiredDocument): RedirectResponse
    {
        $this->documents->update($requiredDocument, $request->validated());

        return to_route('admin.required-documents.index')->with('success', 'Required document updated successfully.');
    }

    public function destroy(ServiceRequiredDocument $requiredDocument): RedirectResponse
    {
        $softDeleted = $this->documents->delete($requiredDocument);

        return to_route('admin.required-documents.index')->with('success', $softDeleted
            ? 'Used document archived successfully; existing request history was preserved.'
            : 'Required document deleted successfully.');
    }

    public function reorder(ReorderServiceRequiredDocumentsRequest $request, Service $service): RedirectResponse
    {
        $this->documents->reorder($service->id, $request->validated('documents'));

        return back()->with('success', 'Document order updated successfully.');
    }

    public function updateMappings(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*.requirement_type' => ['required', Rule::in(ServiceRequiredDocument::REQUIREMENT_TYPES)],
            'mappings.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($data, $service): void {
            foreach ($data['mappings'] as $id => $mapping) {
                $document = $service->requiredDocuments()->whereKey($id)->firstOrFail();
                $document->update([
                    ...$mapping,
                    'is_mandatory' => $mapping['requirement_type'] === 'required',
                    'is_active' => $mapping['requirement_type'] !== 'not_applicable',
                ]);
            }
        });

        return back()->with('success', 'Service document mappings updated successfully.');
    }
}
