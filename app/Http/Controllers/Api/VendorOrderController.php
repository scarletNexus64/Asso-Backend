<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorOrderController extends Controller
{
    /**
     * List orders for vendor (orders containing their products)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get order IDs where this vendor has items
        $orderIds = OrderItem::where('seller_id', $user->id)->pluck('order_id')->unique();

        $query = Order::with(['items' => function($q) use ($user) {
            $q->where('seller_id', $user->id)->with('product.primaryImage');
        }, 'user', 'deliveryPerson'])
            ->whereIn('id', $orderIds);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders->getCollection()->map(fn($order) => $this->formatVendorOrder($order, $user->id)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'has_more' => $orders->hasMorePages(),
            ],
        ]);
    }

    /**
     * Validate (confirm) an order
     */
    public function validate(Request $request, $id)
    {
        $user = $request->user();
        $order = $this->getVendorOrder($user, $id);

        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Cette commande ne peut plus être validée'], 422);
        }

        $order->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande validée',
            'order' => $this->formatVendorOrder($order->fresh(['items.product.primaryImage', 'user', 'deliveryPerson']), $user->id),
        ]);
    }

    /**
     * Reject an order
     */
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $user = $request->user();
        $order = $this->getVendorOrder($user, $id);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Cette commande ne peut plus être refusée'], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->reason ?? 'Refusée par le vendeur',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande refusée',
        ]);
    }

    /**
     * Assign a delivery person to an order
     */
    public function assignDelivery(Request $request, $id)
    {
        $request->validate([
            'delivery_person_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();
        $order = $this->getVendorOrder($user, $id);

        if (!in_array($order->status, ['confirmed', 'preparing'])) {
            return response()->json(['success' => false, 'message' => 'La commande doit être confirmée avant d\'assigner un livreur'], 422);
        }

        // Verify the delivery person has livreur role
        $deliveryPerson = User::where('id', $request->delivery_person_id)
            ->where('role', 'livreur')
            ->first();

        if (!$deliveryPerson) {
            return response()->json(['success' => false, 'message' => 'Livreur non trouvé'], 404);
        }

        $order->update([
            'delivery_person_id' => $deliveryPerson->id,
            'status' => 'preparing',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livreur assigné',
            'delivery_person' => [
                'id' => $deliveryPerson->id,
                'name' => $deliveryPerson->name,
                'phone' => $deliveryPerson->phone,
            ],
        ]);
    }

    /**
     * Get available delivery persons
     */
    public function availableDeliveryPersons(Request $request)
    {
        $deliveryPersons = User::where('role', 'livreur')
            ->select('id', 'first_name', 'last_name', 'phone', 'address', 'avatar', 'latitude', 'longitude')
            ->get();

        return response()->json([
            'success' => true,
            'delivery_persons' => $deliveryPersons->map(fn($dp) => [
                'id' => $dp->id,
                'name' => $dp->name,
                'phone' => $dp->phone,
                'address' => $dp->address,
                'avatar' => $dp->avatar,
                'latitude' => $dp->latitude,
                'longitude' => $dp->longitude,
            ]),
        ]);
    }

    /**
     * Helper to get order that belongs to this vendor
     */
    private function getVendorOrder($user, $orderId)
    {
        $orderIds = OrderItem::where('seller_id', $user->id)->pluck('order_id')->unique();
        return Order::with(['items.product.primaryImage', 'user', 'deliveryPerson'])
            ->whereIn('id', $orderIds)
            ->findOrFail($orderId);
    }

    /**
     * Format vendor order
     */
    private function formatVendorOrder($order, $vendorId): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'delivery_fee' => (float) $order->delivery_fee,
            'delivery_address' => $order->delivery_address,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'customer' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'phone' => $order->user->phone,
            ] : null,
            'delivery_person' => $order->deliveryPerson ? [
                'id' => $order->deliveryPerson->id,
                'name' => $order->deliveryPerson->name,
                'phone' => $order->deliveryPerson->phone,
            ] : null,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Produit',
                'product_image' => $item->product?->primaryImage ? asset('storage/' . $item->product->primaryImage->image_path) : null,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
        ];
    }
}
