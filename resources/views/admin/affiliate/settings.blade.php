@extends('admin.layouts.app')

@section('content')
<div class="p-6 space-y-6">
    <!-- En-tête -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-100">Configuration Affiliation</h1>
            <p class="text-gray-400 mt-1">Gérez les paramètres du programme de parrainage</p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Affiliés</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['total_affiliates'] }}</p>
                </div>
                <div class="bg-blue-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-users text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Commissions</p>
                    <p class="text-3xl font-bold mt-2">{{ number_format($stats['total_commissions'], 0, ',', ' ') }}</p>
                    <p class="text-xs text-green-200 mt-1">XOF</p>
                </div>
                <div class="bg-green-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-coins text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">En attente</p>
                    <p class="text-3xl font-bold mt-2">{{ number_format($stats['pending_commissions'], 0, ',', ' ') }}</p>
                    <p class="text-xs text-yellow-200 mt-1">XOF</p>
                </div>
                <div class="bg-yellow-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-primary-100 text-sm font-medium">Payées</p>
                    <p class="text-3xl font-bold mt-2">{{ number_format($stats['paid_commissions'], 0, ',', ' ') }}</p>
                    <p class="text-xs text-primary-200 mt-1">XOF</p>
                </div>
                <div class="bg-primary-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Parrains Actifs</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['active_referrers'] }}</p>
                </div>
                <div class="bg-purple-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-user-friends text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de configuration -->
    <form action="{{ route('admin.affiliate.update-settings') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Paramètres généraux -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-dark-100 border border-dark-200 rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-100 mb-4">
                        <i class="fas fa-cog text-primary-500 mr-2"></i>
                        Paramètres Généraux
                    </h3>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-dark-50 rounded-lg">
                            <div>
                                <label for="is_enabled" class="font-medium text-gray-200">Activer le système d'affiliation</label>
                                <p class="text-sm text-gray-400 mt-1">Permet aux utilisateurs de parrainer d'autres membres</p>
                            </div>
                            <input type="checkbox" name="is_enabled" id="is_enabled" value="1"
                                {{ $settings->is_enabled ? 'checked' : '' }}
                                class="w-12 h-6 rounded-full">
                        </div>

                        <div class="flex items-center justify-between p-4 bg-dark-50 rounded-lg">
                            <div>
                                <label for="auto_approve_commissions" class="font-medium text-gray-200">Approbation automatique</label>
                                <p class="text-sm text-gray-400 mt-1">Approuver automatiquement les commissions générées</p>
                            </div>
                            <input type="checkbox" name="auto_approve_commissions" id="auto_approve_commissions" value="1"
                                {{ $settings->auto_approve_commissions ? 'checked' : '' }}
                                class="w-12 h-6 rounded-full">
                        </div>

                        <div>
                            <label for="max_levels" class="block text-sm font-medium text-gray-300 mb-2">Nombre de niveaux d'affiliation</label>
                            <select id="max_levels" name="max_levels" required
                                class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                                <option value="1" {{ $settings->max_levels == 1 ? 'selected' : '' }}>1 niveau</option>
                                <option value="2" {{ $settings->max_levels == 2 ? 'selected' : '' }}>2 niveaux</option>
                                <option value="3" {{ $settings->max_levels == 3 ? 'selected' : '' }}>3 niveaux</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Niveau 1 = filleuls directs, Niveau 2 = filleuls des filleuls, etc.</p>
                        </div>

                        <div>
                            <label for="minimum_withdrawal" class="block text-sm font-medium text-gray-300 mb-2">Montant minimum de retrait (XOF)</label>
                            <input type="number" id="minimum_withdrawal" name="minimum_withdrawal" step="0.01" required
                                value="{{ $settings->minimum_withdrawal }}"
                                class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                </div>

                <!-- Pourcentages de commission -->
                <div class="bg-dark-100 border border-dark-200 rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-100 mb-4">
                        <i class="fas fa-percentage text-green-500 mr-2"></i>
                        Pourcentages de Commission
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="level_1_percentage" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Niveau 1 (%)
                            </label>
                            <input type="number" id="level_1_percentage" name="level_1_percentage" step="0.01" min="0" max="100" required
                                value="{{ $settings->level_1_percentage }}"
                                class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-400 mt-1">Filleuls directs</p>
                        </div>

                        <div>
                            <label for="level_2_percentage" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Niveau 2 (%)
                            </label>
                            <input type="number" id="level_2_percentage" name="level_2_percentage" step="0.01" min="0" max="100" required
                                value="{{ $settings->level_2_percentage }}"
                                class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-400 mt-1">Filleuls indirects</p>
                        </div>

                        <div>
                            <label for="level_3_percentage" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Niveau 3 (%)
                            </label>
                            <input type="number" id="level_3_percentage" name="level_3_percentage" step="0.01" min="0" max="100" required
                                value="{{ $settings->level_3_percentage }}"
                                class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-400 mt-1">Niveau 3</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aperçu -->
            <div class="lg:col-span-1">
                <div class="bg-dark-100 border border-dark-200 rounded-lg p-6 sticky top-24">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">
                        <i class="fas fa-calculator text-blue-500 mr-2"></i>
                        Exemple de Calcul
                    </h3>

                    <div class="bg-dark-50 rounded-lg p-4 mb-4">
                        <p class="text-sm text-gray-400 mb-2">Transaction</p>
                        <p class="text-2xl font-bold text-gray-100">10,000 XOF</p>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-900 to-yellow-800 rounded-lg">
                            <span class="text-sm text-yellow-200">Niveau 1 ({{ $settings->level_1_percentage }}%)</span>
                            <span class="font-bold text-yellow-100">{{ number_format(10000 * $settings->level_1_percentage / 100, 0) }} XOF</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-orange-900 to-orange-800 rounded-lg">
                            <span class="text-sm text-orange-200">Niveau 2 ({{ $settings->level_2_percentage }}%)</span>
                            <span class="font-bold text-orange-100">{{ number_format(10000 * $settings->level_2_percentage / 100, 0) }} XOF</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-red-900 to-red-800 rounded-lg">
                            <span class="text-sm text-red-200">Niveau 3 ({{ $settings->level_3_percentage }}%)</span>
                            <span class="font-bold text-red-100">{{ number_format(10000 * $settings->level_3_percentage / 100, 0) }} XOF</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-dark-200">
                        <button type="submit"
                            class="w-full px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer la Configuration
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
