<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier si le mode maintenance est activé
        $maintenanceMode = Setting::get('maintenance_mode', false);

        if (!$maintenanceMode) {
            return $next($request);
        }

        // Ne jamais bloquer les routes admin (le dashboard doit toujours être accessible)
        if ($request->is('admin/*') || $request->is('admin')) {
            return $next($request);
        }

        // Le mode maintenance bloque uniquement l'application mobile (routes API)
        // Si c'est une requête API, retourner une réponse JSON
        if ($request->is('api/*') || $request->expectsJson()) {
            $message = Setting::get('maintenance_message', 'L\'application est actuellement en maintenance. Veuillez réessayer plus tard.');
            $endTime = Setting::get('maintenance_end_time', null);

            return response()->json([
                'success' => false,
                'message' => $message,
                'maintenance' => true,
                'end_time' => $endTime,
            ], 503);
        }

        // Toutes les autres routes (web) passent normalement
        // Le mode maintenance ne les bloque pas
        return $next($request);
    }

}
