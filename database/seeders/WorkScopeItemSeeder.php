<?php

namespace Database\Seeders;

use App\Models\WorkScopeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkScopeItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [['Drafting', 'દસ્તાવેજનું લખાણ'], ['Document Review', 'દસ્તાવેજોની તપાસ'], ['Title Verification', 'ટાઇટલ ચેકિંગ'], ['Stamp Duty Calculation', 'સ્ટેમ્પ ડ્યુટી ગણતરી'], ['Registration Fee Calculation', 'નોંધણી ફી ગણતરી'], ['Garvi Portal Token Booking', 'ગરવી પોર્ટલ ટોકન બુકિંગ'], ['Registration Assistance', 'નોંધણી કામગીરીમાં સહાય'], ['Sub-Registrar Office Work', 'સબ રજિસ્ટ્રાર કચેરી સંબંધિત કામગીરી'], ['Certified Copy', 'પ્રમાણિત નકલ'], ['Revenue Entry Follow-up', 'રેવન્યુ એન્ટ્રી અનુસરણ'], ['Dispatch / Delivery Preparation', 'મોકલવાની તૈયારી'], ['Other', 'અન્ય કામગીરી']];
        foreach ($items as $order => [$en,$gu]) {
            $normalized = Str::of($en)->lower()->squish()->value();
            $item = WorkScopeItem::withTrashed()->firstOrNew(['normalized_name' => $normalized]);
            if ($item->trashed()) {
                $item->restore();
            }$item->fill(['name_en' => $en, 'name_gu' => $gu, 'is_active' => true, 'display_order' => $order + 1])->save();
        }
    }
}
