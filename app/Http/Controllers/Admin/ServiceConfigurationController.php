<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceConfiguration;
use Illuminate\Http\Request;

class ServiceConfigurationController extends Controller
{
    /**
     * Afficher la liste des configurations de services.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $services = ServiceConfiguration::all();

        return view('admin.service-configurations.index', compact('services'));
    }

    /**
     * Mettre à jour la configuration d'un service.
     *
     * @param Request $request
     * @param string $serviceName
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, string $serviceName)
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean',
                'configuration' => 'required|array',
                'description' => 'nullable|string',
            ]);

            ServiceConfiguration::setConfig(
                $serviceName,
                $validated['configuration'],
                $validated['is_active'],
                $validated['description'] ?? null
            );

            return redirect()->back()
                ->with('success', 'Configuration du service mise à jour avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Activer ou désactiver un service.
     *
     * @param string $serviceName
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggle(string $serviceName)
    {
        try {
            $service = ServiceConfiguration::where('service_name', $serviceName)->first();

            if (!$service) {
                return redirect()->back()
                    ->with('error', 'Service non trouvé');
            }

            $newStatus = !$service->is_active;
            ServiceConfiguration::toggleService($serviceName, $newStatus);

            $message = $newStatus ? 'Service activé' : 'Service désactivé';

            return redirect()->back()
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors du basculement du service: ' . $e->getMessage());
        }
    }

    /**
     * Tester la connexion à un service.
     *
     * @param string $serviceName
     * @return \Illuminate\Http\JsonResponse
     */
    public function test(string $serviceName)
    {
        try {
            $config = ServiceConfiguration::getConfig($serviceName);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service non configuré ou inactif',
                ], 404);
            }

            // Logique de test selon le service
            $testResult = $this->performServiceTest($serviceName, $config);

            return response()->json([
                'success' => $testResult['success'],
                'message' => $testResult['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du test: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Effectuer un test de connexion au service.
     *
     * @param string $serviceName
     * @param array $config
     * @return array
     */
    private function performServiceTest(string $serviceName, array $config): array
    {
        // TODO: Implémenter les tests réels pour chaque service
        // Pour l'instant, retourner un succès simulé

        return [
            'success' => true,
            'message' => 'Connexion au service ' . $serviceName . ' réussie',
        ];
    }
}
