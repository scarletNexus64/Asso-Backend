@extends('admin.layouts.app')

@section('title', 'Paramètres')
@section('header', 'Paramètres de l\'Application')

@section('content')
<div class="space-y-6">
    <!-- Tabs Navigation -->
    <div class="bg-dark-100 rounded-lg shadow-sm border border-dark-200" x-data="{ activeTab: new URLSearchParams(window.location.search).get('tab') || 'general' }">
        <div class="border-b border-dark-200">
            <nav class="flex space-x-4 px-6" aria-label="Tabs">
                <button @click="activeTab = 'general'"
                        :class="activeTab === 'general' ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-cog mr-2"></i> Général
                </button>
                <button @click="activeTab = 'system'"
                        :class="activeTab === 'system' ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-server mr-2"></i> Système
                </button>
                <button @click="activeTab = 'commissions'"
                        :class="activeTab === 'commissions' ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-percentage mr-2"></i> Commissions
                </button>
            </nav>
        </div>

        <!-- General Tab -->
        <div x-show="activeTab === 'general'" x-cloak>
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Informations Générales</h3>

                    <!-- App Name -->
                    <div>
                        <label for="app_name" class="block text-sm font-medium text-gray-300 mb-2">
                            Nom de l'application <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="app_name" id="app_name"
                               value="{{ old('app_name', $generalSettings['app_name']->value ?? 'ASSO') }}"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               required>
                        @error('app_name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- App Logo -->
                    <div>
                        <label for="app_logo" class="block text-sm font-medium text-gray-300 mb-2">
                            Logo de l'application
                        </label>
                        @if(isset($generalSettings['app_logo']) && $generalSettings['app_logo']->value)
                            <div class="mb-2">
                                <img src="{{ Storage::url($generalSettings['app_logo']->value) }}" alt="Logo" class="h-16 rounded">
                            </div>
                        @endif
                        <input type="file" name="app_logo" id="app_logo" accept="image/*"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-500 file:text-white hover:file:bg-primary-600">
                    </div>

                    <!-- App Slogan -->
                    <div>
                        <label for="app_slogan" class="block text-sm font-medium text-gray-300 mb-2">
                            Slogan
                        </label>
                        <input type="text" name="app_slogan" id="app_slogan"
                               value="{{ old('app_slogan', $generalSettings['app_slogan']->value ?? '') }}"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <!-- App Description -->
                    <div>
                        <label for="app_description" class="block text-sm font-medium text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea name="app_description" id="app_description" rows="4"
                                  class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">{{ old('app_description', $generalSettings['app_description']->value ?? '') }}</textarea>
                    </div>

                    <h3 class="text-lg font-semibold text-white mb-4 mt-8">Informations de Contact</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Email -->
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-300 mb-2">
                                Email de contact <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="contact_email" id="contact_email"
                                   value="{{ old('contact_email', $generalSettings['contact_email']->value ?? '') }}"
                                   class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   required>
                        </div>

                        <!-- Contact Phone -->
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-300 mb-2">
                                Téléphone
                            </label>
                            <input type="text" name="contact_phone" id="contact_phone"
                                   value="{{ old('contact_phone', $generalSettings['contact_phone']->value ?? '') }}"
                                   class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Contact Address -->
                    <div>
                        <label for="contact_address" class="block text-sm font-medium text-gray-300 mb-2">
                            Adresse
                        </label>
                        <input type="text" name="contact_address" id="contact_address"
                               value="{{ old('contact_address', $generalSettings['contact_address']->value ?? '') }}"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4">
                        <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-md">
                            <i class="fas fa-save mr-2"></i> Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- System Tab -->
        <div x-show="activeTab === 'system'" x-cloak>
            <form action="{{ route('admin.settings.update') }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Paramètres Système</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Timezone -->
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-300 mb-2">
                                Fuseau horaire <span class="text-red-500">*</span>
                            </label>
                            <select name="timezone" id="timezone"
                                    class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    required>
                                <option value="Africa/Porto-Novo" {{ old('timezone', $systemSettings['timezone']->value ?? '') == 'Africa/Porto-Novo' ? 'selected' : '' }}>Africa/Porto-Novo</option>
                                <option value="Africa/Abidjan" {{ old('timezone', $systemSettings['timezone']->value ?? '') == 'Africa/Abidjan' ? 'selected' : '' }}>Africa/Abidjan</option>
                                <option value="Africa/Lagos" {{ old('timezone', $systemSettings['timezone']->value ?? '') == 'Africa/Lagos' ? 'selected' : '' }}>Africa/Lagos</option>
                                <option value="UTC" {{ old('timezone', $systemSettings['timezone']->value ?? '') == 'UTC' ? 'selected' : '' }}>UTC</option>
                            </select>
                        </div>

                        <!-- Default Language -->
                        <div>
                            <label for="default_language" class="block text-sm font-medium text-gray-300 mb-2">
                                Langue par défaut <span class="text-red-500">*</span>
                            </label>
                            <select name="default_language" id="default_language"
                                    class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    required>
                                <option value="fr" {{ old('default_language', $systemSettings['default_language']->value ?? '') == 'fr' ? 'selected' : '' }}>Français</option>
                                <option value="en" {{ old('default_language', $systemSettings['default_language']->value ?? '') == 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-300 mb-2">
                                Devise <span class="text-red-500">*</span>
                            </label>
                            <select name="currency" id="currency"
                                    class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    required>
                                <option value="XOF" {{ old('currency', $systemSettings['currency']->value ?? '') == 'XOF' ? 'selected' : '' }}>XOF (Franc CFA)</option>
                                <option value="USD" {{ old('currency', $systemSettings['currency']->value ?? '') == 'USD' ? 'selected' : '' }}>USD (Dollar)</option>
                                <option value="EUR" {{ old('currency', $systemSettings['currency']->value ?? '') == 'EUR' ? 'selected' : '' }}>EUR (Euro)</option>
                            </select>
                        </div>

                        <!-- Currency Symbol -->
                        <div>
                            <label for="currency_symbol" class="block text-sm font-medium text-gray-300 mb-2">
                                Symbole de la devise <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="currency_symbol" id="currency_symbol"
                                   value="{{ old('currency_symbol', $systemSettings['currency_symbol']->value ?? '') }}"
                                   class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   required>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4">
                        <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-md">
                            <i class="fas fa-save mr-2"></i> Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Commissions Tab -->
        @php
            $rangesData = $commissionRanges->map(fn($r) => [
                'id' => $r->id,
                'min_amount' => $r->min_amount,
                'max_amount' => $r->max_amount,
                'percentage' => $r->percentage,
                'is_active' => $r->is_active,
            ])->values()->toArray();
            if (empty($rangesData)) {
                $rangesData = [['id' => null, 'min_amount' => '', 'max_amount' => '', 'percentage' => '', 'is_active' => true]];
            }
        @endphp
        <div x-show="activeTab === 'commissions'" x-cloak
             x-data="commissionRanges()">
            <form action="{{ route('admin.settings.commissions.update') }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Commissions par plage de montant</h3>
                            <p class="text-sm text-gray-400 mt-1">Configurez le pourcentage de commission qu'Asso prélève sur chaque transaction en fonction du montant.</p>
                        </div>
                        <button type="button" @click="addRange()"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                            <i class="fas fa-plus mr-1"></i> Ajouter une plage
                        </button>
                    </div>

                    <!-- Table Header -->
                    <div class="hidden md:grid md:grid-cols-12 gap-4 px-4 py-2 text-sm font-medium text-gray-400 border-b border-dark-200">
                        <div class="col-span-3">Montant minimum (F)</div>
                        <div class="col-span-3">Montant maximum (F)</div>
                        <div class="col-span-2">Commission (%)</div>
                        <div class="col-span-2 text-center">Actif</div>
                        <div class="col-span-2 text-center">Actions</div>
                    </div>

                    <!-- Ranges -->
                    <template x-for="(range, index) in ranges" :key="index">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 px-4 py-3 bg-dark-50 rounded-lg border border-dark-300 items-center">
                            <!-- Min Amount -->
                            <div class="col-span-1 md:col-span-3">
                                <label class="md:hidden block text-xs text-gray-400 mb-1">Montant minimum (F)</label>
                                <input type="number" :name="'ranges[' + index + '][min_amount]'" x-model="range.min_amount"
                                       placeholder="1000" min="0" step="1"
                                       class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       required>
                            </div>

                            <!-- Max Amount -->
                            <div class="col-span-1 md:col-span-3">
                                <label class="md:hidden block text-xs text-gray-400 mb-1">Montant maximum (F)</label>
                                <input type="number" :name="'ranges[' + index + '][max_amount]'" x-model="range.max_amount"
                                       placeholder="100000" min="0" step="1"
                                       class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       required>
                            </div>

                            <!-- Percentage -->
                            <div class="col-span-1 md:col-span-2">
                                <label class="md:hidden block text-xs text-gray-400 mb-1">Commission (%)</label>
                                <div class="relative">
                                    <input type="number" :name="'ranges[' + index + '][percentage]'" x-model="range.percentage"
                                           placeholder="5" min="0" max="100" step="0.01"
                                           class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm pr-8"
                                           required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                                </div>
                            </div>

                            <!-- Active Toggle -->
                            <div class="col-span-1 md:col-span-2 flex items-center md:justify-center">
                                <label class="md:hidden text-xs text-gray-400 mr-2">Actif</label>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :name="'ranges[' + index + '][is_active]'" x-model="range.is_active"
                                           value="1" class="sr-only peer">
                                    <div class="w-11 h-6 bg-dark-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                                </label>
                            </div>

                            <!-- Remove Button -->
                            <div class="col-span-1 md:col-span-2 flex items-center md:justify-center">
                                <button type="button" @click="removeRange(index)" x-show="ranges.length > 1"
                                        class="px-3 py-2 bg-red-600/20 text-red-400 rounded-lg hover:bg-red-600/40 transition-colors text-sm">
                                    <i class="fas fa-trash-alt mr-1"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Info -->
                    <div class="bg-blue-900/20 border border-blue-800/30 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-400 mt-0.5 mr-3"></i>
                            <div class="text-sm text-blue-300">
                                <p class="font-medium mb-1">Comment fonctionnent les plages de commission ?</p>
                                <p class="text-blue-400">Pour chaque transaction, le système vérifie dans quelle plage de montant se situe le prix du produit et applique le pourcentage de commission correspondant. Assurez-vous que les plages ne se chevauchent pas.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4">
                        <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-md">
                            <i class="fas fa-save mr-2"></i> Enregistrer les commissions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function commissionRanges() {
        return {
            ranges: @json($rangesData),
            addRange() {
                this.ranges.push({ id: null, min_amount: '', max_amount: '', percentage: '', is_active: true });
            },
            removeRange(index) {
                if (this.ranges.length > 1) {
                    this.ranges.splice(index, 1);
                }
            }
        }
    }
</script>
@endpush
