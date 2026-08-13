<?php

namespace Database\Seeders;

use App\Models\CommonRequiredDocument;
use App\Models\Service;
use App\Models\ServiceRequiredDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CentralRequiredDocumentsSeeder extends Seeder
{
    private const DOCUMENTS = [
        ['7-12-extract', '7/12 Extract', '૭/૧૨ ઉતારો', ['7/12 extract']],
        ['8-a-extract', '8-A Extract', '૮-અ ઉતારો', ['8-a extract']],
        ['hak-patrak-village-form-6', 'Hak Patrak / Village Form No. 6', 'હક્કપત્રક / ગામ નમૂના નં. 6', ['hak patrak']],
        ['property-card', 'Property Card', 'પ્રોપર્ટી કાર્ડ', ['property card']],
        ['assessment-register-village-form-2', 'Assessment Register / Village Form No. 2', 'આકારણી પત્રક / ગામ નમૂના નં. 2', []],
        ['previous-deed', 'Previous Deed', 'અગાઉનો દસ્તાવેજ', ['previous deed']],
        ['index-2', 'Index-2', 'ઇન્ડેક્સ-2', []],
        ['mutation-entry', 'Mutation Entry', 'ફેરફાર નોંધ', []],
        ['na-order', 'NA Order', 'બિનખેતી હુકમ', []],
        ['layout-plan', 'Layout / Plan', 'લેઆઉટ / પ્લાન', []],
        ['building-permission-plan', 'Building Permission and Plan', 'બિલ્ડિંગ પરમિશન અને પ્લાન', []],
        ['tax-bill', 'Tax Bill', 'ટેક્સ બિલ', []],
        ['possession-allotment-letter', 'Possession / Allotment Letter', 'કબજા / એલોટમેન્ટ લેટર', []],
        ['society-association-documents', 'Society / Association Documents', 'સોસાયટી / એસોસિએશનના દસ્તાવેજો', []],
        ['bank-noc', 'Bank NOC', 'બેંક NOC', []],
        ['mortgage-deed', 'Mortgage Deed', 'ગીરોખત', []],
        ['mortgage-release-deed', 'Mortgage Release Deed', 'ગીરો મુક્તિનો દસ્તાવેજ', []],
        ['other', 'Other', 'અન્ય', []],
    ];

    private const ANY_ONE = ['7-12-extract', 'property-card', 'assessment-register-village-form-2'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $masters = [];
            $newMasterCodes = [];
            foreach (self::DOCUMENTS as $order => [$code, $nameEn, $nameGu]) {
                $master = CommonRequiredDocument::withTrashed()->where('code', $code)->first() ?? new CommonRequiredDocument;
                if ($master->trashed()) {
                    $master->restore();
                }
                $master->fill([
                    'code' => $code, 'name_en' => $nameEn, 'name_gu' => $nameGu,
                    'normalized_name' => $code, 'display_order' => $order + 1,
                    'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                    'max_upload_size_kb' => 10240, 'is_active' => true, 'is_common' => true,
                ])->save();
                $masters[$code] = $master;
                if ($master->wasRecentlyCreated) {
                    $newMasterCodes[] = $code;
                }
            }

            Service::query()->whereIn('slug', ServiceCommercialConfigurationSeeder::serviceSlugs())->get()->each(
                function (Service $service) use ($masters, $newMasterCodes): void {
                    foreach (self::DOCUMENTS as $order => [$code]) {
                        $master = $masters[$code];
                        $type = in_array($code, self::ANY_ONE, true) ? 'any_one_required' : 'optional';
                        $mapping = ServiceRequiredDocument::withTrashed()->firstOrNew([
                            'service_id' => $service->id,
                            'common_required_document_id' => $master->id,
                        ]);
                        if ($mapping->trashed()) {
                            $mapping->restore();
                        }
                        if (! $mapping->exists || in_array($code, $newMasterCodes, true)) {
                            $mapping->fill([
                                'name_en' => $master->name_en, 'name_gu' => $master->name_gu,
                                'requirement_type' => $type, 'is_mandatory' => false, 'is_active' => true,
                                'sort_order' => $order + 1, 'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                                'max_upload_size_kb' => 10240,
                            ])->save();
                        }
                    }
                },
            );
        });
    }
}
