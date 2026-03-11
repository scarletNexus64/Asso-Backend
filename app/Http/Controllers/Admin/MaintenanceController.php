<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Activer le mode maintenance.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function enable(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'nullable|string',
                'end_time' => 'nullable|string',
            ]);

            Setting::set('maintenance_mode', true, 'boolean', 'maintenance', 'Mode maintenance activé');

            if (isset($validated['message'])) {
                Setting::set('maintenance_message', $validated['message'], 'text', 'maintenance', 'Message de maintenance');
            }

            if (isset($validated['end_time'])) {
                Setting::set('maintenance_end_time', $validated['end_time'], 'string', 'maintenance', 'Heure de fin estimée');
            }

            return redirect()->back()
                ->with('success', 'Mode maintenance activé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de l\'activation du mode maintenance: ' . $e->getMessage());
        }
    }

    /**
     * Désactiver le mode maintenance.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function disable()
    {
        try {
            Setting::set('maintenance_mode', false, 'boolean', 'maintenance', 'Mode maintenance désactivé');

            return redirect()->back()
                ->with('success', 'Mode maintenance désactivé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la désactivation du mode maintenance: ' . $e->getMessage());
        }
    }

    /**
     * Basculer le mode maintenance.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggle()
    {
        try {
            $currentMode = Setting::get('maintenance_mode', false);
            $newMode = !$currentMode;

            Setting::set('maintenance_mode', $newMode, 'boolean', 'maintenance');

            $message = $newMode ? 'Mode maintenance activé' : 'Mode maintenance désactivé';

            return redirect()->back()
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors du basculement du mode maintenance: ' . $e->getMessage());
        }
    }
}
