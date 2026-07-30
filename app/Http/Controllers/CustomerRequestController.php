<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequestRequest;
use App\Models\CustomerRequest;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class CustomerRequestController extends Controller
{
    /**
     * Display Request Form
     */
    public function create()
    {
        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('frontend.request.create', compact('services'));
    }

    /**
     * Store Customer Request
     */
    public function store(StoreCustomerRequestRequest $request)
    {
        DB::beginTransaction();

        try {

            // Generate Reference Number
            $lastRequest = CustomerRequest::latest('id')->first();

            $nextNumber = $lastRequest
                ? ((int) substr($lastRequest->reference_no, -6)) + 1
                : 1;

            $referenceNo = 'SC/' . date('Y') . '/' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // Save Request
            $customerRequest = CustomerRequest::create([
                'reference_no'   => $referenceNo,
                'service_id'     => $request->service_id,
                'name'           => $request->name,
                'mobile'         => $request->mobile,
                'email'          => $request->email,
                'village'        => $request->village,
                'taluka'         => $request->taluka,
                'district'       => $request->district,
                'survey_numbers' => $request->survey_numbers,
                'khata_number'   => $request->khata_number,
                'details'        => $request->details,
                'status'         => 'received',
            ]);

            DB::commit();

            return redirect()
                ->route('request.success')
                ->with([
                    'reference_no' => $customerRequest->reference_no,
                    'success' => 'તમારી વિનંતી સફળતાપૂર્વક નોંધાઈ ગઈ છે.'
                ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Success Page
     */
   public function success()
{
    if (!session()->has('reference_no')) {
        return redirect()->route('request.create');
    }

    return view('frontend.request.success');
}
}
