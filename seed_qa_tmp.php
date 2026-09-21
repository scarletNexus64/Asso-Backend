<?php
// Seed QA : partenaire livreur local à Douala + zone + grille au poids,
// livreur synchronisé, et plages de commission ASSO.

use App\Models\DelivererCompany;
use App\Models\DeliveryZone;
use App\Models\DeliveryPricelist;
use App\Models\CommissionRange;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

// --- 1. Plages de commission ASSO (majoration sur le prix vendeur) ---
CommissionRange::truncate();
foreach ([
    ['min' => 0,      'max' => 9999,      'pct' => 10],
    ['min' => 10000,  'max' => 49999,     'pct' => 8],
    ['min' => 50000,  'max' => 199999,    'pct' => 6],
    ['min' => 200000, 'max' => 99999999,  'pct' => 5],
] as $r) {
    CommissionRange::create([
        'min_amount' => $r['min'],
        'max_amount' => $r['max'],
        'percentage' => $r['pct'],
        'is_active'  => true,
    ]);
}
echo "✅ commission_ranges: " . CommissionRange::count() . " plages\n";

// --- 2. Livreur (user) dédié QA ---
$deliverer = User::updateOrCreate(
    ['phone' => '+237690000777'],
    [
        'first_name' => 'Livreur',
        'last_name'  => 'QA Douala',
        'email'      => 'livreur.qa@asso.test',
        'password'   => Hash::make('password'),
        'role'       => 'livreur',
        'roles'      => json_encode(['livreur']),
        'country'    => 'Cameroun',
        'is_profile_complete' => true,
    ]
);
echo "✅ livreur user #{$deliverer->id} ({$deliverer->phone})\n";

// --- 3. Société de livraison locale à Douala ---
$company = DelivererCompany::updateOrCreate(
    ['name' => 'QA Express Douala'],
    [
        'user_id'      => $deliverer->id,
        'phone'        => $deliverer->phone,
        'email'        => $deliverer->email,
        'description'  => 'Partenaire QA : livraison urbaine à Douala.',
        'is_active'    => true,
        'service_type' => 'local',
        'service_mode' => 'door_to_door',
        'prices_exclude_vat' => false,
        'max_weight_kg'      => 50,
    ]
);
echo "✅ deliverer_company #{$company->id} {$company->name}\n";

// --- 4. Zone Douala (centre-ville) ---
$zone = DeliveryZone::updateOrCreate(
    ['deliverer_company_id' => $company->id, 'name' => 'Douala Centre'],
    [
        'city'             => 'Douala',
        'center_latitude'  => 4.05000000,
        'center_longitude' => 9.70000000,
        'is_active'        => true,
        'zone_data'        => json_encode(['radius_km' => 15]),
    ]
);
echo "✅ delivery_zone #{$zone->id} {$zone->name} ({$zone->city})\n";

// --- 5. Grille tarifaire au poids réel ---
$pricelist = DeliveryPricelist::updateOrCreate(
    ['delivery_zone_id' => $zone->id],
    [
        'pricing_type'    => DeliveryPricelist::PRICING_TYPE_VOLUMETRIC_WEIGHT,
        'pricing_data'    => [
            'ranges' => [
                ['min' => 0, 'max' => 2,  'price' => 1500, 'label' => 'Plis & paquets'],
                ['min' => 2, 'max' => 10, 'price' => 2500, 'label' => 'Colis'],
                ['min' => 10,'max' => 30, 'price' => 5000, 'label' => 'Gros colis'],
            ],
            'extra_per_kg' => 200,
        ],
        'asso_commission' => 0,
        'lead_time'       => '24 h',
        'is_active'       => true,
    ]
);
echo "✅ delivery_pricelist #{$pricelist->id} type={$pricelist->pricing_type}\n";

// --- 6. Synchronisation du livreur à la société (pour /delivery/pending) ---
$syncCode = DB::table('deliverer_sync_codes')->where('company_id', $company->id)->first();
if (!$syncCode) {
    $id = DB::table('deliverer_sync_codes')->insertGetId([
        'company_id' => $company->id,
        'sync_code'  => 'QA-DOUALA-0001',
        'user_id'    => $deliverer->id,
        'is_used'    => true,
        'used_at'    => now(),
        'expires_at' => now()->addYear(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $syncCode = (object) ['id' => $id];
}
DB::table('deliverer_code_syncs')->updateOrInsert(
    ['user_id' => $deliverer->id, 'company_id' => $company->id],
    [
        'sync_code_id' => $syncCode->id,
        'is_active'    => true,
        'is_banned'    => false,
        'synced_at'    => now(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]
);
echo "✅ livreur synchronisé à la société (sync_code #{$syncCode->id})\n";

echo "\n--- Vérification ---\n";
echo "Zones actives: " . DeliveryZone::where('is_active', true)->count() . "\n";
echo "Pricelists actives: " . DeliveryPricelist::where('is_active', true)->count() . "\n";
echo "Plages commission: " . CommissionRange::where('is_active', true)->count() . "\n";
