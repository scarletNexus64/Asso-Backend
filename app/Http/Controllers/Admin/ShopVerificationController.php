<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopVerificationController extends Controller
{
    /**
     * Verify/Approve a shop
     */
    public function verify($shopId)
    {
        $shop = Shop::findOrFail($shopId);

        if ($shop->isVerified()) {
            return redirect()->back()
                ->with('info', "La boutique {$shop->name} est déjà vérifiée.");
        }

        $shop->update([
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ]);

        // TODO: Send FCM notification to vendor
        // $fcmService->sendToUser($shop->user, [
        //     'title' => '🎉 Boutique vérifiée !',
        //     'body' => "Félicitations ! Votre boutique \"{$shop->name}\" a été vérifiée.",
        //     'data' => [
        //         'type' => 'shop_verified',
        //         'shop_id' => (string) $shop->id,
        //         'action' => 'open_vendor_dashboard',
        //     ],
        // ]);

        \Log::info("[SHOP_VERIFICATION] Shop verified", [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'verified_by' => auth()->id(),
        ]);

        return redirect()->back()
            ->with('success', "Boutique {$shop->name} vérifiée avec succès !");
    }

    /**
     * Reject a shop
     */
    public function reject(Request $request, $shopId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $shop = Shop::findOrFail($shopId);

        $shop->update([
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'verified_at' => null,
            'verified_by' => null,
        ]);

        // TODO: Send FCM notification to vendor
        // $fcmService->sendToUser($shop->user, [
        //     'title' => '❌ Boutique non vérifiée',
        //     'body' => "Votre boutique \"{$shop->name}\" n'a pas été approuvée.",
        //     'data' => [
        //         'type' => 'shop_rejected',
        //         'shop_id' => (string) $shop->id,
        //         'reason' => $request->reason,
        //         'action' => 'open_vendor_dashboard',
        //     ],
        // ]);

        \Log::info("[SHOP_VERIFICATION] Shop rejected", [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'reason' => $request->reason,
            'rejected_by' => auth()->id(),
        ]);

        return redirect()->back()
            ->with('success', "Boutique {$shop->name} rejetée");
    }

    /**
     * Toggle shop status (activate/deactivate)
     */
    public function toggleStatus($shopId)
    {
        $shop = Shop::findOrFail($shopId);

        $newStatus = $shop->status === 'active' ? 'inactive' : 'active';

        $shop->update([
            'status' => $newStatus,
        ]);

        $message = $newStatus === 'active'
            ? "Boutique {$shop->name} activée"
            : "Boutique {$shop->name} désactivée";

        return redirect()->back()
            ->with('success', $message);
    }
}
