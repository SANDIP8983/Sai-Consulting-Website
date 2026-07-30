<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequestRequest;
use App\Models\CustomerRequest;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class CustomerRequestController extends Controller
{
    public function create()
    {
        $services = Service::query()->where('is_active', true)->with('requiredDocuments')->orderBy('sort_order')->orderBy('name_en')->get();
        return view('frontend.request.create', compact('services'));
    }

    public function store(StoreCustomerRequestRequest $request)
    {
        DB::beginTransaction();
        try {
            $lastRequest = CustomerRequest::latest('id')->first();
            $nextNumber = $lastRequest ? ((int) substr($lastRequest->reference_no, -6)) + 1 : 1;
            $customerRequest = CustomerRequest::create(['reference_no' => 'SC/' . date('Y') . '/' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT), 'service_id' => $request->service_id, 'name' => $request->name, 'mobile' => $request->mobile, 'email' => $request->email, 'village' => $request->village, 'taluka' => $request->taluka, 'district' => $request->district, 'survey_numbers' => $request->survey_numbers, 'khata_number' => $request->khata_number, 'details' => $request->details, 'status' => 'received']);
            DB::commit();
            return redirect()->route('request.success')->with('reference_no', $customerRequest->reference_no);
        } catch (\Exception $exception) {
            DB::rollBack();
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function success()
    {
        return session()->has('reference_no') ? view('frontend.request.success') : redirect()->route('request.create');
    }
}
