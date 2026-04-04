<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List user orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.product.primaryImage', 'items.product.images', 'deliveryPerson'])
            ->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'orders' => $orders->getCollection()->map(fn($order) => $this->formatOrder($order)),
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
     * Show single order
     */
    public function show(Request $request, $id)
    {
        $order = Order::with(['items.product.primaryImage', 'items.product.images', 'items.seller', 'deliveryPerson'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'order' => $this->formatOrder($order, true),
        ]);
    }

    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'nullable|string',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            'payment_method' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $price = (float) $product->price;
                $quantity = $item['quantity'];
                $totalPrice = $price * $quantity;
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'seller_id' => $product->user_id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total_price' => $totalPrice,
                ];
            }

            $deliveryFee = $request->get('delivery_fee', 2000);
            $total = $subtotal + $deliveryFee;

            $order = Order::create([
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'delivery_address' => $request->delivery_address,
                'delivery_latitude' => $request->delivery_latitude,
                'delivery_longitude' => $request->delivery_longitude,
                'payment_method' => $request->payment_method ?? 'mobile',
                'notes' => $request->notes,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            $order->load(['items.product.primaryImage', 'items.product.images']);

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'order' => $this->formatOrder($order),
            ], 201);
        });
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->reason,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande annulée',
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Format order for API response
     */
    private function formatOrder($order, $detailed = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'delivery_fee' => (float) $order->delivery_fee,
            'total' => (float) $order->total,
            'formatted_total' => $order->formatted_total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'tracking_number' => $order->tracking_number,
            'delivery_address' => $order->delivery_address,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Produit supprimé',
                'product_image' => $item->product?->primaryImage
                    ? asset('storage/' . $item->product->primaryImage->image_path)
                    : null,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
            ]),
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->toIso8601String(),
        ];

        if ($order->deliveryPerson) {
            $data['delivery_person'] = [
                'id' => $order->deliveryPerson->id,
                'name' => $order->deliveryPerson->name,
                'phone' => $order->deliveryPerson->phone,
                'avatar' => $order->deliveryPerson->avatar,
            ];
        }

        if ($detailed) {
            $data['notes'] = $order->notes;
            $data['cancel_reason'] = $order->cancel_reason;
            $data['confirmed_at'] = $order->confirmed_at?->toIso8601String();
            $data['shipped_at'] = $order->shipped_at?->toIso8601String();
            $data['delivered_at'] = $order->delivered_at?->toIso8601String();
            $data['cancelled_at'] = $order->cancelled_at?->toIso8601String();
        }

        return $data;
    }
}
