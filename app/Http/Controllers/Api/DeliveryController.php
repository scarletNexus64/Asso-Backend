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
