<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Afficher la page principale des paramètres.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $generalSettings = Setting::where('group', 'general')->get()->keyBy('key');
        $systemSettings = Setting::where('group', 'system')->get()->keyBy('key');

        return view('admin.settings.index', compact('generalSettings', 'systemSettings'));
    }

    /**
     * Mettre à jour les paramètres généraux.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'app_name' => 'required|string|max:255',
                'app_slogan' => 'nullable|string|max:255',
                'app_description' => 'nullable|string',
                'contact_email' => 'required|email',
                'contact_phone' => 'nullable|string|max:20',
                'contact_address' => 'nullable|string|max:255',
                'timezone' => 'required|string',
                'default_language' => 'required|string',
                'currency' => 'required|string|max:10',
                'currency_symbol' => 'required|string|max:10',
            ]);

            // Gérer l'upload du logo
            if ($request->hasFile('app_logo')) {
                $validated['app_logo'] = $request->file('app_logo')->store('settings', 'public');
                Setting::set('app_logo', $validated['app_logo'], 'file', 'general', 'Logo de l\'application');
            }

            // Mettre à jour tous les paramètres
            foreach ($validated as $key => $value) {
                if ($key === 'app_logo') {
                    continue; // Déjà géré ci-dessus
                }

                $type = in_array($key, ['app_description']) ? 'text' : 'string';
                $group = in_array($key, ['timezone', 'default_language', 'currency', 'currency_symbol']) ? 'system' : 'general';

                Setting::set($key, $value, $type, $group);
            }

            return redirect()->route('admin.settings.index')
                ->with('success', 'Paramètres mis à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour des paramètres: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Afficher la page de gestion de la maintenance.
     *
     * @return \Illuminate\View\View
     */
    public function maintenance()
    {
        $maintenanceSettings = Setting::where('group', 'maintenance')->get()->keyBy('key');

        return view('admin.settings.maintenance', compact('maintenanceSettings'));
    }

    /**
     * Mettre à jour les paramètres de maintenance.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateMaintenance(Request $request)
    {
        try {
            $validated = $request->validate([
                'maintenance_mode' => 'required|boolean',
                'maintenance_message' => 'nullable|string',
                'maintenance_end_time' => 'nullable|string',
            ]);

            Setting::set('maintenance_mode', $validated['maintenance_mode'], 'boolean', 'maintenance', 'Mode maintenance activé');
            Setting::set('maintenance_message', $validated['maintenance_message'] ?? '', 'text', 'maintenance', 'Message de maintenance');
            Setting::set('maintenance_end_time', $validated['maintenance_end_time'] ?? '', 'string', 'maintenance', 'Heure de fin estimée');

            return redirect()->route('admin.settings.maintenance')
                ->with('success', 'Paramètres de maintenance mis à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Afficher la page de configuration des paiements.
     *
     * @return \Illuminate\View\View
     */
    public function payments()
    {
        $paymentSettings = Setting::where('group', 'payment')->get()->keyBy('key');

        return view('admin.settings.payments', compact('paymentSettings'));
    }

    /**
     * Mettre à jour les paramètres de paiement.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePayments(Request $request)
    {
        try {
            $validated = $request->validate([
                // PayPal
                'paypal_enabled' => 'nullable|boolean',
                'paypal_mode' => 'nullable|in:sandbox,live',
                'paypal_client_id' => 'nullable|string',
                'paypal_secret' => 'nullable|string',
                'paypal_webhook_id' => 'nullable|string',
                'paypal_currency' => 'nullable|string|in:USD,EUR,XOF',
                // Fedapay
                'fedapay_enabled' => 'nullable|boolean',
                'fedapay_mode' => 'nullable|in:sandbox,live',
                'fedapay_public_key' => 'nullable|string',
                'fedapay_secret_key' => 'nullable|string',
                'fedapay_webhook_secret' => 'nullable|string',
                'fedapay_currency' => 'nullable|string|in:XOF,XAF,NGN',
                'fedapay_callback_url' => 'nullable|url',
                'fedapay_timeout' => 'nullable|integer|min:60|max:600',
                'fedapay_auto_commission' => 'nullable|boolean',
            ]);

            foreach ($validated as $key => $value) {
                // Déterminer le type
                $type = 'string';
                if (str_ends_with($key, '_enabled') || str_ends_with($key, '_commission')) {
                    $type = 'boolean';
                } elseif ($key === 'fedapay_timeout') {
                    $type = 'integer';
                }

                Setting::set($key, $value ?? '', $type, 'payment');
            }

            return redirect()->route('admin.settings.payments')
                ->with('success', 'Paramètres de paiement mis à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Afficher la page de configuration des services.
     *
     * @return \Illuminate\View\View
     */
    public function services()
    {
        $smsSettings = Setting::where('group', 'sms')->get()->keyBy('key');
        $whatsappSettings = Setting::where('group', 'whatsapp')->get()->keyBy('key');

        return view('admin.settings.services', compact('smsSettings', 'whatsappSettings'));
    }

    /**
     * Mettre à jour les paramètres des services.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateServices(Request $request)
    {
        try {
            $validated = $request->validate([
                // Nexaah SMS
                'nexaah_enabled' => 'nullable|boolean',
                'nexaah_api_key' => 'nullable|string',
                'nexaah_api_secret' => 'nullable|string',
                'nexaah_account_sid' => 'nullable|string',
                'nexaah_sender_id' => 'nullable|string|max:11',
                'nexaah_base_url' => 'nullable|url',
                'nexaah_country_code' => 'nullable|string|max:5',
                'nexaah_timeout' => 'nullable|integer|min:10|max:120',
                // WhatsApp
                'whatsapp_enabled' => 'nullable|boolean',
                'whatsapp_business_account_id' => 'nullable|string',
                'whatsapp_phone_number_id' => 'nullable|string',
                'whatsapp_business_phone' => 'nullable|string',
                'whatsapp_display_name' => 'nullable|string|max:255',
                'whatsapp_access_token' => 'nullable|string',
                'whatsapp_app_id' => 'nullable|string',
                'whatsapp_app_secret' => 'nullable|string',
                'whatsapp_api_version' => 'nullable|string|in:v18.0,v19.0,v20.0',
                'whatsapp_webhook_verify_token' => 'nullable|string',
            ]);

            foreach ($validated as $key => $value) {
                // Déterminer le type
                $type = 'string';
                if (str_ends_with($key, '_enabled')) {
                    $type = 'boolean';
                } elseif ($key === 'nexaah_timeout') {
                    $type = 'integer';
                }

                // Déterminer le groupe
                $group = str_starts_with($key, 'nexaah') ? 'sms' : 'whatsapp';

                Setting::set($key, $value ?? '', $type, $group);
            }

            return redirect()->route('admin.settings.services')
                ->with('success', 'Paramètres des services mis à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }
}
