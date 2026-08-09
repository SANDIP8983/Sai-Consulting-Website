<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationMilestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHolidayRequest;
use App\Http\Requests\Admin\UpdateCompanyBrandingRequest;
use App\Http\Requests\Admin\UpdateContactSettingsRequest;
use App\Http\Requests\Admin\UpdateCustomerNotificationSettingsRequest;
use App\Http\Requests\Admin\UpdateHolidayRequest;
use App\Http\Requests\Admin\UpdateOfficeSettingsRequest;
use App\Http\Requests\Admin\UpdateOfficeTimingsRequest;
use App\Http\Requests\Admin\UpdateWebsiteSettingsRequest;
use App\Models\Holiday;
use App\Services\BrandingAssetService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function website(): View
    {
        return view('admin.settings.website', [
            'settings' => $this->settingsService->websiteSettings(),
        ]);
    }

    public function companyBranding(): View
    {
        return view('admin.settings.company-branding', [
            'settings' => $this->settingsService->companyBrandingSettings(),
        ]);
    }

    public function updateCompanyBranding(UpdateCompanyBrandingRequest $request, BrandingAssetService $assets): RedirectResponse
    {
        $current = $this->settingsService->companyBrandingSettings();
        $validated = $request->validated();
        $textFields = ['business_name', 'tagline', 'address', 'mobile', 'whatsapp', 'email', 'website_url', 'gstin'];
        $values = array_merge($current, array_intersect_key($validated, array_flip($textFields)), $assets->updatedPaths($validated, $current));
        $this->settingsService->updateCompanyBrandingSettings($values);

        return to_route('admin.settings.company-branding')->with('success', 'Company information and branding updated successfully.');
    }

    public function updateWebsite(UpdateWebsiteSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateWebsiteSettings($request->validated());

        return to_route('admin.settings.website')->with('success', 'Website settings updated successfully.');
    }

    public function office(): View
    {
        return view('admin.settings.office', [
            'settings' => $this->settingsService->officeSettings(),
        ]);
    }

    public function updateOffice(UpdateOfficeSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateOfficeSettings($request->validated());

        return to_route('admin.settings.office')->with('success', 'Office settings updated successfully.');
    }

    public function contact(): View
    {
        return view('admin.settings.contact', [
            'settings' => $this->settingsService->contactSettings(),
        ]);
    }

    public function updateContact(UpdateContactSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateContactSettings($request->validated());

        return to_route('admin.settings.contact')->with('success', 'Contact settings updated successfully.');
    }

    public function customerNotifications(): View
    {
        return view('admin.settings.customer-notifications', ['settings' => $this->settingsService->customerNotificationSettings(), 'milestones' => NotificationMilestone::cases()]);
    }

    public function updateCustomerNotifications(UpdateCustomerNotificationSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateCustomerNotificationSettings($request->validated('milestones'));

        return to_route('admin.settings.customer-notifications')->with('success', 'Customer notification settings updated successfully.');
    }

    public function officeTimings(): View
    {
        return view('admin.settings.office-timings', [
            'timings' => $this->settingsService->officeTimings(),
        ]);
    }

    public function updateOfficeTimings(UpdateOfficeTimingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateOfficeTimings($request->validated('timings'));

        return to_route('admin.settings.office-timings')->with('success', 'Office timings updated successfully.');
    }

    public function holidays(): View
    {
        return view('admin.settings.holidays', [
            'holidays' => $this->settingsService->holidays(),
            'editingHoliday' => null,
        ]);
    }

    public function editHoliday(Holiday $holiday): View
    {
        return view('admin.settings.holidays', [
            'holidays' => $this->settingsService->holidays(),
            'editingHoliday' => $holiday,
        ]);
    }

    public function storeHoliday(StoreHolidayRequest $request): RedirectResponse
    {
        $this->settingsService->createHoliday($request->validated());

        return to_route('admin.settings.holidays')->with('success', 'Holiday created successfully.');
    }

    public function updateHoliday(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->settingsService->updateHoliday($holiday, $request->validated());

        return to_route('admin.settings.holidays')->with('success', 'Holiday updated successfully.');
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $this->settingsService->deleteHoliday($holiday);

        return to_route('admin.settings.holidays')->with('success', 'Holiday deleted successfully.');
    }
}
