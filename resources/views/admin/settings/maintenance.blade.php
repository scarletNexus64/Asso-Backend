@extends('admin.layouts.app')

@section('title', 'Mode Maintenance')
@section('header', 'Gestion du Mode Maintenance')

@section('content')
<div class="space-y-6">
    @php
        $isMaintenanceMode = isset($maintenanceSettings['maintenance_mode']) && $maintenanceSettings['maintenance_mode']->value == '1';
    @endphp

    <!-- Status Header Card -->
    <div class="bg-gradient-to-br from-{{ $isMaintenanceMode ? 'red' : 'green' }}-500/10 to-{{ $isMaintenanceMode ? 'red' : 'green' }}-600/5 rounded-xl shadow-lg border border-{{ $isMaintenanceMode ? 'red' : 'green' }}-500/20 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-gradient-to-br from-{{ $isMaintenanceMode ? 'red' : 'green' }}-500 to-{{ $isMaintenanceMode ? 'red' : 'green' }}-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-{{ $isMaintenanceMode ? 'exclamation-triangle' : 'check-circle' }} text-3xl text-white"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-white">Mode Maintenance</h3>
                    <p class="text-gray-400 mt-1">
                        @if($isMaintenanceMode)
                            Le site est actuellement en maintenance
                        @else
                            Le site est en ligne et accessible
                        @endif
                    </p>
                </div>
            </div>
            <!-- Status Badge -->
            <div>
                @if($isMaintenanceMode)
                    <span class="inline-flex items-center px-6 py-3 rounded-lg bg-red-500/20 text-red-400 border border-red-500/50 text-sm font-semibold">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Maintenance Activée
                    </span>
                @else
                    <span class="inline-flex items-center px-6 py-3 rounded-lg bg-green-500/20 text-green-400 border border-green-500/50 text-sm font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>
                        Site en Ligne
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Maintenance Form -->
    <form action="{{ route('admin.settings.maintenance.update') }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Main Configuration Card -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
            <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                <i class="fas fa-cog text-primary-500 mr-2"></i>
                Configuration du Mode Maintenance
            </h4>

            <div class="space-y-6">
                <!-- Maintenance Mode Toggle -->
                <div class="flex items-center justify-between p-5 bg-dark-50 rounded-lg border border-dark-300">
                    <div class="flex-1">
                        <label for="maintenance_mode" class="text-base font-medium text-white flex items-center">
                            <i class="fas fa-power-off text-primary-500 mr-2"></i>
                            Activer le mode maintenance
                        </label>
                        <p class="text-sm text-gray-400 mt-2 ml-6">
                            Lorsqu'activé, l'application mobile sera bloquée et affichera le message de maintenance. Le dashboard admin reste toujours accessible.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1"
                               {{ $isMaintenanceMode ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-8 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-500/20 rounded-full peer peer-checked:after:translate-x-8 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-primary-500 peer-checked:to-primary-600 shadow-inner"></div>
                        <span class="ml-3 text-sm font-medium text-gray-300">
                            <span x-show="document.getElementById('maintenance_mode').checked" class="text-primary-400">Activé</span>
                            <span x-show="!document.getElementById('maintenance_mode').checked" class="text-gray-500">Désactivé</span>
                        </span>
                    </label>
                </div>

                <!-- Maintenance Message -->
                <div>
                    <label for="maintenance_message" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-comment-alt text-primary-400 mr-1"></i>
                        Message de maintenance <span class="text-red-500">*</span>
                    </label>
                    <textarea name="maintenance_message" id="maintenance_message" rows="5"
                              class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                              placeholder="Nous effectuons une maintenance technique. Nous serons de retour très bientôt.">{{ old('maintenance_message', $maintenanceSettings['maintenance_message']->value ?? '') }}</textarea>
                    <p class="mt-2 text-xs text-gray-500 flex items-start">
                        <i class="fas fa-info-circle text-primary-500 mr-1 mt-0.5"></i>
                        Ce message sera affiché aux utilisateurs pendant la maintenance
                    </p>
                    @error('maintenance_message')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Maintenance End Time -->
                <div>
                    <label for="maintenance_end_time" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-clock text-primary-400 mr-1"></i>
                        Heure de fin estimée
                    </label>
                    <input type="datetime-local" name="maintenance_end_time" id="maintenance_end_time"
                           value="{{ old('maintenance_end_time', $maintenanceSettings['maintenance_end_time']->value ?? '') }}"
                           class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <p class="mt-2 text-xs text-gray-500 flex items-start">
                        <i class="fas fa-info-circle text-primary-500 mr-1 mt-0.5"></i>
                        Indiquez quand la maintenance devrait se terminer (optionnel)
                    </p>
                </div>
            </div>
        </div>

        <!-- Warning Box -->
        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mt-1"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-base font-semibold text-yellow-400 mb-2">Attention - Impact sur l'application mobile</h4>
                    <ul class="text-sm text-yellow-300/80 space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-yellow-500 mr-2 mt-0.5"></i>
                            <span>L'application mobile sera bloquée pour tous les utilisateurs</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-yellow-500 mr-2 mt-0.5"></i>
                            <span>L'API retournera un code 503 (Service Unavailable)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-yellow-500 mr-2 mt-0.5"></i>
                            <span>Le dashboard admin reste accessible en toute circonstance</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-yellow-500 mr-2 mt-0.5"></i>
                            <span>Assurez-vous d'informer vos utilisateurs mobiles à l'avance</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500 text-2xl mt-1"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-base font-semibold text-blue-400 mb-2">Bonnes pratiques</h4>
                    <ul class="text-sm text-blue-300/80 space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                            <span>Planifiez la maintenance pendant les heures creuses</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                            <span>Prévenez les utilisateurs 24-48h à l'avance</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                            <span>Indiquez une durée estimée et respectez-la</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                            <span>Testez les modifications avant de désactiver la maintenance</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                            <span>Vous pourrez toujours désactiver la maintenance depuis le dashboard admin</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-between items-center">
            <a href="{{ route('admin.settings.index') }}"
               class="px-6 py-3 bg-dark-300 text-white rounded-lg hover:bg-dark-400 transition-all shadow-md">
                <i class="fas fa-arrow-left mr-2"></i> Retour
            </a>
            <button type="submit"
                    class="px-8 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-save mr-2"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection
