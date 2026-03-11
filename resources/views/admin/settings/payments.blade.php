@extends('admin.layouts.app')

@section('title', 'Paramètres de Paiement')
@section('header', 'Configuration des Paiements')

@section('content')
<div class="space-y-6" x-data="{ activePayment: 'paypal' }">
    <!-- Sticky Tabs Navigation -->
    <div class="bg-dark-100 rounded-lg shadow-lg border border-dark-200 sticky top-0 z-10">
        <div class="border-b border-dark-200">
            <nav class="flex space-x-4 px-6" aria-label="Payment Tabs">
                <button @click="activePayment = 'paypal'"
                        :class="activePayment === 'paypal' ? 'border-blue-500 text-blue-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fab fa-paypal mr-2"></i> PayPal
                </button>
                <button @click="activePayment = 'fedapay'"
                        :class="activePayment === 'fedapay' ? 'border-green-500 text-green-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-money-bill-wave mr-2"></i> Fedapay
                </button>
            </nav>
        </div>
    </div>

    <!-- PayPal Tab -->
    <div x-show="activePayment === 'paypal'" x-cloak>
        <form action="{{ route('admin.settings.payments.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="payment_type" value="paypal">

            <!-- Service Header Card -->
            <div class="bg-gradient-to-br from-blue-500/10 to-blue-600/5 rounded-xl shadow-lg border border-blue-500/20 p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fab fa-paypal text-3xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">PayPal</h3>
                            <p class="text-gray-400 mt-1">Acceptez les paiements via PayPal dans le monde entier</p>
                        </div>
                    </div>
                    <!-- Enable/Disable Toggle -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="paypal_enabled" value="1"
                               {{ old('paypal_enabled', $paymentSettings['paypal_enabled']->value ?? '0') == '1' ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-8 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-500/20 rounded-full peer peer-checked:after:translate-x-8 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-blue-500 peer-checked:to-blue-600 shadow-inner"></div>
                        <span class="ml-3 text-sm font-medium text-gray-300">
                            <span x-show="$el.previousElementSibling.querySelector('input').checked" class="text-blue-400">Activé</span>
                            <span x-show="!$el.previousElementSibling.querySelector('input').checked" class="text-gray-500">Désactivé</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Configuration Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-cog text-blue-500 mr-2"></i>
                    Configuration de l'API PayPal
                </h4>

                <div class="space-y-6">
                    <!-- Environment Mode -->
                    <div>
                        <label for="paypal_mode" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-server text-blue-400 mr-1"></i> Environnement <span class="text-red-500">*</span>
                        </label>
                        <select name="paypal_mode" id="paypal_mode"
                                class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                required>
                            <option value="sandbox" {{ old('paypal_mode', $paymentSettings['paypal_mode']->value ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox (Test)</option>
                            <option value="live" {{ old('paypal_mode', $paymentSettings['paypal_mode']->value ?? '') == 'live' ? 'selected' : '' }}>Live (Production)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Utilisez Sandbox pour tester avant de passer en Live</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Client ID -->
                        <div>
                            <label for="paypal_client_id" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-key text-blue-400 mr-1"></i> Client ID <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="paypal_client_id" id="paypal_client_id"
                                       value="{{ old('paypal_client_id', $paymentSettings['paypal_client_id']->value ?? '') }}"
                                       class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="Entrez votre PayPal Client ID">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Identifiant client de votre application PayPal</p>
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <label for="paypal_secret" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-lock text-blue-400 mr-1"></i> Secret Key <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" name="paypal_secret" id="paypal_secret"
                                       value="{{ old('paypal_secret', $paymentSettings['paypal_secret']->value ?? '') }}"
                                       class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="Entrez votre PayPal Secret Key">
                                <button type="button" onclick="togglePassword('paypal_secret')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Clé secrète de votre application PayPal</p>
                        </div>

                        <!-- Webhook ID -->
                        <div>
                            <label for="paypal_webhook_id" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-webhook text-blue-400 mr-1"></i> Webhook ID
                            </label>
                            <input type="text" name="paypal_webhook_id" id="paypal_webhook_id"
                                   value="{{ old('paypal_webhook_id', $paymentSettings['paypal_webhook_id']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                   placeholder="ID du webhook PayPal">
                            <p class="mt-1 text-xs text-gray-500">Identifiant du webhook pour les notifications</p>
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="paypal_currency" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-dollar-sign text-blue-400 mr-1"></i> Devise par défaut
                            </label>
                            <select name="paypal_currency" id="paypal_currency"
                                    class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="USD" {{ old('paypal_currency', $paymentSettings['paypal_currency']->value ?? 'USD') == 'USD' ? 'selected' : '' }}>USD - Dollar américain</option>
                                <option value="EUR" {{ old('paypal_currency', $paymentSettings['paypal_currency']->value ?? '') == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                <option value="XOF" {{ old('paypal_currency', $paymentSettings['paypal_currency']->value ?? '') == 'XOF' ? 'selected' : '' }}>XOF - Franc CFA</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Devise utilisée pour les transactions PayPal</p>
                        </div>
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
                        <h4 class="text-base font-semibold text-blue-400 mb-2">Comment configurer PayPal ?</h4>
                        <ul class="text-sm text-blue-300/80 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                                <span>Créez un compte développeur sur <a href="https://developer.paypal.com" target="_blank" class="underline hover:text-blue-300">developer.paypal.com</a></span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                                <span>Créez une application REST API pour obtenir vos identifiants</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                                <span>Configurez les webhooks pour recevoir les notifications de paiement</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-blue-500 mr-2 mt-0.5"></i>
                                <span>Testez en mode Sandbox avant de passer en production</span>
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
                        class="px-8 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i> Enregistrer la configuration
                </button>
            </div>
        </form>
    </div>

    <!-- Fedapay Tab -->
    <div x-show="activePayment === 'fedapay'" x-cloak>
        <form action="{{ route('admin.settings.payments.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="payment_type" value="fedapay">

            <!-- Service Header Card -->
            <div class="bg-gradient-to-br from-green-500/10 to-green-600/5 rounded-xl shadow-lg border border-green-500/20 p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fas fa-money-bill-wave text-3xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">Fedapay</h3>
                            <p class="text-gray-400 mt-1">Solution de paiement mobile money pour l'Afrique de l'Ouest</p>
                        </div>
                    </div>
                    <!-- Enable/Disable Toggle -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="fedapay_enabled" value="1"
                               {{ old('fedapay_enabled', $paymentSettings['fedapay_enabled']->value ?? '0') == '1' ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-8 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-500/20 rounded-full peer peer-checked:after:translate-x-8 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-green-500 peer-checked:to-green-600 shadow-inner"></div>
                        <span class="ml-3 text-sm font-medium text-gray-300">
                            <span x-show="$el.previousElementSibling.querySelector('input').checked" class="text-green-400">Activé</span>
                            <span x-show="!$el.previousElementSibling.querySelector('input').checked" class="text-gray-500">Désactivé</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Configuration Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-key text-green-500 mr-2"></i>
                    Configuration de l'API Fedapay
                </h4>

                <div class="space-y-6">
                    <!-- Environment Mode -->
                    <div>
                        <label for="fedapay_mode" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-server text-green-400 mr-1"></i> Environnement <span class="text-red-500">*</span>
                        </label>
                        <select name="fedapay_mode" id="fedapay_mode"
                                class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                required>
                            <option value="sandbox" {{ old('fedapay_mode', $paymentSettings['fedapay_mode']->value ?? 'sandbox') == 'sandbox' ? 'selected' : '' }}>Sandbox (Test)</option>
                            <option value="live" {{ old('fedapay_mode', $paymentSettings['fedapay_mode']->value ?? '') == 'live' ? 'selected' : '' }}>Live (Production)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Utilisez Sandbox pour tester avant de passer en Live</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Public Key -->
                        <div>
                            <label for="fedapay_public_key" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-key text-green-400 mr-1"></i> Public Key <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="fedapay_public_key" id="fedapay_public_key"
                                       value="{{ old('fedapay_public_key', $paymentSettings['fedapay_public_key']->value ?? '') }}"
                                       class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                       placeholder="pk_sandbox_... ou pk_live_...">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Clé publique pour initialiser les paiements</p>
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <label for="fedapay_secret_key" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-lock text-green-400 mr-1"></i> Secret Key <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" name="fedapay_secret_key" id="fedapay_secret_key"
                                       value="{{ old('fedapay_secret_key', $paymentSettings['fedapay_secret_key']->value ?? '') }}"
                                       class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                       placeholder="sk_sandbox_... ou sk_live_...">
                                <button type="button" onclick="togglePassword('fedapay_secret_key')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Clé secrète pour sécuriser les transactions</p>
                        </div>

                        <!-- Webhook Secret -->
                        <div>
                            <label for="fedapay_webhook_secret" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-shield-alt text-green-400 mr-1"></i> Webhook Secret
                            </label>
                            <div class="relative">
                                <input type="password" name="fedapay_webhook_secret" id="fedapay_webhook_secret"
                                       value="{{ old('fedapay_webhook_secret', $paymentSettings['fedapay_webhook_secret']->value ?? '') }}"
                                       class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                       placeholder="Secret pour valider les webhooks">
                                <button type="button" onclick="togglePassword('fedapay_webhook_secret')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Secret pour vérifier l'authenticité des webhooks</p>
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="fedapay_currency" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-money-bill text-green-400 mr-1"></i> Devise par défaut
                            </label>
                            <select name="fedapay_currency" id="fedapay_currency"
                                    class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                                <option value="XOF" {{ old('fedapay_currency', $paymentSettings['fedapay_currency']->value ?? 'XOF') == 'XOF' ? 'selected' : '' }}>XOF - Franc CFA (Bénin)</option>
                                <option value="XAF" {{ old('fedapay_currency', $paymentSettings['fedapay_currency']->value ?? '') == 'XAF' ? 'selected' : '' }}>XAF - Franc CFA (Cameroun)</option>
                                <option value="NGN" {{ old('fedapay_currency', $paymentSettings['fedapay_currency']->value ?? '') == 'NGN' ? 'selected' : '' }}>NGN - Naira (Nigeria)</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Devise utilisée pour les transactions</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-cogs text-green-500 mr-2"></i>
                    Paramètres Avancés
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Callback URL -->
                    <div class="md:col-span-2">
                        <label for="fedapay_callback_url" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-link text-green-400 mr-1"></i> URL de Callback
                        </label>
                        <input type="url" name="fedapay_callback_url" id="fedapay_callback_url"
                               value="{{ old('fedapay_callback_url', $paymentSettings['fedapay_callback_url']->value ?? url('/api/v1/payments/fedapay/callback')) }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="https://votre-site.com/api/v1/payments/fedapay/callback">
                        <p class="mt-1 text-xs text-gray-500">URL de retour après paiement</p>
                    </div>

                    <!-- Timeout -->
                    <div>
                        <label for="fedapay_timeout" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-clock text-green-400 mr-1"></i> Timeout (secondes)
                        </label>
                        <input type="number" name="fedapay_timeout" id="fedapay_timeout"
                               value="{{ old('fedapay_timeout', $paymentSettings['fedapay_timeout']->value ?? '300') }}"
                               min="60" max="600"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="300">
                        <p class="mt-1 text-xs text-gray-500">Durée de validité d'une transaction</p>
                    </div>

                    <!-- Auto Commission -->
                    <div class="flex items-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="fedapay_auto_commission" value="1"
                                   {{ old('fedapay_auto_commission', $paymentSettings['fedapay_auto_commission']->value ?? '1') == '1' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            <span class="ml-3 text-sm font-medium text-gray-300">Frais automatiques</span>
                        </label>
                        <p class="ml-3 text-xs text-gray-500">Fedapay calcule automatiquement les frais</p>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-6 mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-green-500 text-2xl mt-1"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-base font-semibold text-green-400 mb-2">Comment configurer Fedapay ?</h4>
                        <ul class="text-sm text-green-300/80 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Créez un compte sur <a href="https://fedapay.com" target="_blank" class="underline hover:text-green-300">fedapay.com</a></span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Accédez à votre tableau de bord et récupérez vos clés API (publique et secrète)</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Configurez les webhooks pour recevoir les notifications de paiement</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Testez en mode Sandbox avec les numéros de test Fedapay</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Activez MTN Mobile Money, Moov Money et autres opérateurs</span>
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
                        class="px-8 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i> Enregistrer la configuration
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.target.closest('button').querySelector('i');

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
@endpush
@endsection
