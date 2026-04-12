<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Get pending delivery requests for this delivery person
     */
    public function pendingRequests(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['items.product.primaryImage', 'user'])
            ->where('delivery_person_id', $user->id)
            ->whereIn('status', ['preparing', 'confirmed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'requests' => $orders->map(fn($order) => $this->formatDeliveryRequest($order)),
        ]);
    }

    /**
     * Accept a delivery request
     */
    public function accept(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('delivery_person_id', $user->id)
            ->whereIn('status', ['preparing', 'confirmed'])
            ->findOrFail($id);

        $order->update(['status' => 'shipped', 'shipped_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison acceptée - course démarrée',
        ]);
    }

    /**
     * Complete a delivery
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('delivery_person_id', $user->id)
            ->where('status', 'shipped')
            ->findOrFail($id);

        $order->update(['status' => 'delivered', 'delivered_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison terminée - en attente de confirmation client',
        ]);
    }

    /**
     * Get active deliveries (shipped status)
     */
    public function activeDeliveries(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['items.product.primaryImage', 'user'])
            ->where('delivery_person_id', $user->id)
            ->where('status', 'shipped')
            ->orderBy('shipped_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'deliveries' => $orders->map(fn($order) => $this->formatDeliveryRequest($order)),
        ]);
    }

    /**
     * Check if a location is within any delivery zone
     */
    public function checkDeliveryAvailability(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        \Log::info('========================================');
        \Log::info('🚚 DELIVERY AVAILABILITY CHECK START');
        \Log::info('========================================');
        \Log::info('📍 Requested Position:', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Get all active delivery zones with their companies and users
        $deliveryZones = \App\Models\DeliveryZone::where('is_active', true)
            ->with(['delivererCompany.user'])
            ->get();

        \Log::info('📦 Total Active Delivery Zones Found: ' . $deliveryZones->count());

        if ($deliveryZones->isEmpty()) {
            \Log::warning('⚠️ No active delivery zones found in database');
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Aucune zone de livraison disponible pour le moment',
            ]);
        }

        // Log each zone with detailed info
        \Log::info('📋 Analyzing Each Delivery Zone:');
        \Log::info('----------------------------------------');

        // Check if the point is within any delivery zone
        // Simple radius-based check using center coordinates
        $nearbyZones = [];
        $zonesWithSyncedDeliverers = 0;
        $zonesWithoutSyncedDeliverers = 0;

        foreach ($deliveryZones as $index => $zone) {
            $zoneNumber = $index + 1;
            \Log::info("📍 Zone #{$zoneNumber}: {$zone->name}");
            \Log::info("  ├─ Zone ID: {$zone->id}");
            \Log::info("  ├─ Company: {$zone->delivererCompany->name}");
            \Log::info("  ├─ Company ID: {$zone->delivererCompany->id}");

            // Check if company has a synchronized deliverer (user)
            $hasSyncedDeliverer = $zone->delivererCompany->user_id !== null;
            \Log::info("  ├─ Has Synced Deliverer: " . ($hasSyncedDeliverer ? 'YES ✅' : 'NO ❌'));

            if ($hasSyncedDeliverer) {
                $zonesWithSyncedDeliverers++;
                $delivererUser = $zone->delivererCompany->user;
                \Log::info("  ├─ Deliverer User ID: {$zone->delivererCompany->user_id}");
                if ($delivererUser) {
                    \Log::info("  ├─ Deliverer Name: {$delivererUser->first_name} {$delivererUser->last_name}");
                    \Log::info("  ├─ Deliverer Phone: {$delivererUser->phone}");
                    \Log::info("  ├─ Deliverer Role: {$delivererUser->role}");
                }
            } else {
                $zonesWithoutSyncedDeliverers++;
                \Log::info("  ├─ ⚠️ Company has NO synchronized deliverer yet");
            }

            if ($zone->center_latitude && $zone->center_longitude) {
                \Log::info("  ├─ Zone Center: ({$zone->center_latitude}, {$zone->center_longitude})");

                // Calculate distance in kilometers using Haversine formula
                $distance = $this->calculateDistance(
                    $latitude,
                    $longitude,
                    (float)$zone->center_latitude,
                    (float)$zone->center_longitude
                );

                \Log::info("  ├─ Distance from requested position: " . round($distance, 2) . " km");

                // Consider zone available if within 10km radius
                // This is a simple check; you can implement more complex polygon checking
                $isWithinRadius = $distance <= 10;
                \Log::info("  ├─ Within 10km radius: " . ($isWithinRadius ? 'YES ✅' : 'NO ❌'));

                if ($isWithinRadius && $hasSyncedDeliverer) {
                    \Log::info("  └─ ✅ ZONE QUALIFIES (within radius AND has synced deliverer)");
                    $nearbyZones[] = [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'distance' => round($distance, 2),
                        'company' => [
                            'id' => $zone->delivererCompany->id,
                            'name' => $zone->delivererCompany->name,
                            'user_id' => $zone->delivererCompany->user_id,
                        ],
                    ];
                } elseif ($isWithinRadius && !$hasSyncedDeliverer) {
                    \Log::info("  └─ ⚠️ ZONE EXCLUDED (within radius but NO synced deliverer)");
                } else {
                    \Log::info("  └─ ❌ ZONE EXCLUDED (outside 10km radius)");
                }
            } else {
                \Log::info("  ├─ Zone Center: NOT SET");
                \Log::info("  └─ ❌ ZONE EXCLUDED (no center coordinates)");
            }

            \Log::info('');
        }

        \Log::info('========================================');
        \Log::info('📊 SUMMARY:');
        \Log::info("  ├─ Total Zones Analyzed: {$deliveryZones->count()}");
        \Log::info("  ├─ Zones with Synced Deliverers: {$zonesWithSyncedDeliverers}");
        \Log::info("  ├─ Zones without Synced Deliverers: {$zonesWithoutSyncedDeliverers}");
        \Log::info("  └─ Qualifying Zones (nearby + synced): " . count($nearbyZones));
        \Log::info('========================================');

        if (empty($nearbyZones)) {
            \Log::warning('❌ RESULT: No qualifying delivery zones found');
            \Log::warning('Reason: Either no zones within radius OR no zones have synced deliverers');
            \Log::info('========================================');

            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Désolé, aucune zone de livraison ne couvre cette position. Veuillez choisir un emplacement dans une zone desservie.',
            ]);
        }

        // Sort by distance
        usort($nearbyZones, fn($a, $b) => $a['distance'] <=> $b['distance']);

        \Log::info('✅ RESULT: Delivery Available!');
        \Log::info('📍 Qualifying Zones (sorted by distance):');
        foreach ($nearbyZones as $index => $zone) {
            $rank = $index + 1;
            \Log::info("  {$rank}. {$zone['name']} - {$zone['distance']} km - Company: {$zone['company']['name']}");
        }
        \Log::info('🏆 Nearest Zone: ' . $nearbyZones[0]['name'] . ' (' . $nearbyZones[0]['distance'] . ' km)');
        \Log::info('========================================');

        return response()->json([
            'success' => true,
            'available' => true,
            'message' => 'Zone de livraison disponible',
            'zones' => $nearbyZones,
            'nearest_zone' => $nearbyZones[0] ?? null,
        ]);
    }

    /**
     * Get all delivery partners (deliverers) with their positions
     * This is used to display deliverers on the map for vendors
     */
    public function getDeliveryPartners(Request $request)
    {
        \Log::info('========================================');
        \Log::info('📍 GET DELIVERY PARTNERS REQUEST');
        \Log::info('========================================');

        try {
            // Get all active delivery companies with their zones
            $deliveryCompanies = \App\Models\DelivererCompany::where('is_active', true)
                ->whereNotNull('user_id') // Only synced deliverers
                ->with(['deliveryZones' => function($query) {
                    $query->where('is_active', true)
                          ->whereNotNull('center_latitude')
                          ->whereNotNull('center_longitude');
                }, 'user'])
                ->get();

            \Log::info("✅ Found {$deliveryCompanies->count()} active delivery companies");

            // Format the response
            $deliverers = [];
            foreach ($deliveryCompanies as $company) {
                foreach ($company->deliveryZones as $zone) {
                    $deliverers[] = [
                        'id' => $company->id,
                        'name' => $company->name,
                        'phone' => $company->phone,
                        'email' => $company->email,
                        'description' => $company->description,
                        'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                        'zone' => [
                            'id' => $zone->id,
                            'name' => $zone->name,
                            'latitude' => (float) $zone->center_latitude,
                            'longitude' => (float) $zone->center_longitude,
                        ],
                        'user' => $company->user ? [
                            'id' => $company->user->id,
                            'name' => $company->user->first_name . ' ' . $company->user->last_name,
                            'phone' => $company->user->phone,
                        ] : null,
                    ];
                }
            }

            \Log::info("✅ Returning " . count($deliverers) . " delivery partners with positions");
            \Log::info('========================================');

            return response()->json([
                'success' => true,
                'deliverers' => $deliverers,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error fetching delivery partners: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::info('========================================');

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des partenaires de livraison',
                'deliverers' => [],
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function formatDeliveryRequest($order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => (float) $order->total,
            'delivery_fee' => (float) $order->delivery_fee,
            'delivery_address' => $order->delivery_address,
            'delivery_latitude' => $order->delivery_latitude,
            'delivery_longitude' => $order->delivery_longitude,
            'customer' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'phone' => $order->user->phone,
                'address' => $order->user->address,
            ] : null,
            'items' => $order->items->map(fn($item) => [
                'product_name' => $item->product->name ?? 'Produit',
                'quantity' => $item->quantity,
            ]),
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->toIso8601String(),
            'shipped_at' => $order->shipped_at?->toIso8601String(),
        ];
    }
}
