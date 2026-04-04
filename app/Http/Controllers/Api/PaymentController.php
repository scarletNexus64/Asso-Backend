<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\FreemopayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Initialize payment for an order
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:freemopay,mobile,paypal,cash',
            'phone_number' => 'required_if:payment_method,freemopay,mobile|string',
        ]);

        $order = Order::where('user_id', $request->user()->id)
            ->where('payment_status', 'pending')
            ->findOrFail($request->order_id);

        if ($request->payment_method === 'freemopay' || $request->payment_method === 'mobile') {
            $freemopay = new FreemopayService();
            $result = $freemopay->initializePayment([
                'amount' => (int) $order->total,
                'currency' => 'XAF',
                'phone_number' => $request->phone_number,
                'description' => "Commande {$order->order_number}",
                'external_reference' => $order->order_number,
            ]);

            if ($result['success']) {
                // Create transaction record
                $transaction = Transaction::create([
                    'reference' => 'TXN' . strtoupper(substr(md5(uniqid()), 0, 10)),
                    'buyer_id' => $request->user()->id,
                    'amount' => $order->total,
                    'currency' => 'XAF',
                    'status' => 'pending',
                    'type' => 'purchase',
                    'payment_method' => 'mobile',
                    'external_reference' => $result['reference'] ?? null,
                    'description' => "Paiement commande {$order->order_number}",
                    'metadata' => ['order_id' => $order->id, 'freemopay_data' => $result['data']],
                    'payer_name' => $request->user()->name,
                ]);

                $order->update([
                    'payment_method' => 'mobile',
                    'payment_reference' => $result['reference'] ?? $transaction->reference,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement initié. Veuillez valider sur votre téléphone.',
                    'payment_reference' => $result['reference'],
                    'transaction_id' => $transaction->id,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        if ($request->payment_method === 'cash') {
            $order->update([
                'payment_method' => 'cash',
                'payment_status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande confirmée. Paiement à la livraison.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Méthode de paiement non supportée',
        ], 422);
    }

    /**
     * Check payment status
     */
    public function status(Request $request, $reference)
    {
        $freemopay = new FreemopayService();
        $result = $freemopay->checkStatus($reference);

        return response()->json([
            'success' => true,
            'payment_status' => $result['status'],
            'data' => $result['data'],
        ]);
    }

    /**
     * FreemoPay webhook callback
     */
    public function webhookFreemopay(Request $request)
    {
        Log::info('FreemoPay webhook received', $request->all());

        $reference = $request->input('reference');
        $status = $request->input('status');

        if (!$reference || !$status) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $transaction = Transaction::where('external_reference', $reference)->first();

        if (!$transaction) {
            Log::warning('FreemoPay webhook: transaction not found', ['reference' => $reference]);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $orderId = $transaction->metadata['order_id'] ?? null;

        if ($status === 'SUCCESS') {
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($orderId) {
                $order = Order::with('items')->find($orderId);
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                    ]);

                    // Add to sellers' pending_earnings (money locked until client confirms)
                    $sellerTotals = [];
                    foreach ($order->items as $item) {
                        $sellerId = $item->seller_id;
                        if (!isset($sellerTotals[$sellerId])) {
                            $sellerTotals[$sellerId] = 0;
                        }
                        $sellerTotals[$sellerId] += (float) $item->total_price;
                    }

                    foreach ($sellerTotals as $sellerId => $amount) {
                        \App\Models\User::where('id', $sellerId)
                            ->increment('pending_earnings', $amount);
                    }
                }
            }
        } elseif ($status === 'FAILED') {
            $transaction->update(['status' => 'cancelled']);

            if ($orderId) {
                Order::where('id', $orderId)->update(['payment_status' => 'failed']);
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }
}
