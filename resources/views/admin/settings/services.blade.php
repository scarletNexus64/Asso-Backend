@extends('admin.layouts.app')

@section('title', 'Services Externes')
@section('header', 'Configuration des Services')

@section('content')
<div class="space-y-6" x-data="{ activeService: 'nexaah' }">
    <!-- Sticky Tabs Navigation -->
    <div class="bg-dark-100 rounded-lg shadow-lg border border-dark-200 sticky top-0 z-10">
        <div class="border-b border-dark-200">
            <nav class="flex space-x-4 px-6" aria-label="Services Tabs">
                <button @click="activeService = 'nexaah'"
                        :class="activeService === 'nexaah' ? 'border-purple-500 text-purple-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fas fa-sms mr-2"></i> Nexaah SMS
                </button>
                <button @click="activeService = 'whatsapp'"
                        :class="activeService === 'whatsapp' ? 'border-green-500 text-green-500' : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i> WhatsApp Business
                </button>
            </nav>
        </div>
    </div>

    <!-- Nexaah SMS Tab -->
    <div x-show="activeService === 'nexaah'" x-cloak>
        <form action="{{ route('admin.settings.services.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="service_type" value="nexaah">

            <!-- Service Header Card -->
            <div class="bg-gradient-to-br from-purple-500/10 to-purple-600/5 rounded-xl shadow-lg border border-purple-500/20 p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fas fa-sms text-3xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">Nexaah SMS</h3>
                            <p class="text-gray-400 mt-1">Service d'envoi de SMS en Afrique via l'API Nexaah</p>
                        </div>
                    </div>
                    <!-- Enable/Disable Toggle -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="nexaah_enabled" value="1"
                               {{ old('nexaah_enabled', $smsSettings['nexaah_enabled']->value ?? '0') == '1' ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-8 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-500/20 rounded-full peer peer-checked:after:translate-x-8 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-purple-500 peer-checked:to-purple-600 shadow-inner"></div>
                        <span class="ml-3 text-sm font-medium text-gray-300">
                            <span x-show="$el.previousElementSibling.querySelector('input').checked" class="text-purple-400">Activé</span>
                            <span x-show="!$el.previousElementSibling.querySelector('input').checked" class="text-gray-500">Désactivé</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Configuration Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-key text-purple-500 mr-2"></i>
                    Identifiants API
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- API Key -->
                    <div>
                        <label for="nexaah_api_key" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-key text-purple-400 mr-1"></i> API Key <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="nexaah_api_key" id="nexaah_api_key"
                                   value="{{ old('nexaah_api_key', $smsSettings['nexaah_api_key']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                   placeholder="Entrez votre clé API Nexaah">
                            <button type="button" onclick="togglePassword('nexaah_api_key')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Clé d'authentification fournie par Nexaah</p>
                    </div>

                    <!-- API Secret -->
                    <div>
                        <label for="nexaah_api_secret" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-lock text-purple-400 mr-1"></i> API Secret <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="nexaah_api_secret" id="nexaah_api_secret"
                                   value="{{ old('nexaah_api_secret', $smsSettings['nexaah_api_secret']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                   placeholder="Entrez votre secret API">
                            <button type="button" onclick="togglePassword('nexaah_api_secret')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Clé secrète pour sécuriser les requêtes</p>
                    </div>

                    <!-- Account SID -->
                    <div>
                        <label for="nexaah_account_sid" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-user-circle text-purple-400 mr-1"></i> Account SID
                        </label>
                        <input type="text" name="nexaah_account_sid" id="nexaah_account_sid"
                               value="{{ old('nexaah_account_sid', $smsSettings['nexaah_account_sid']->value ?? '') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="Ex: ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        <p class="mt-1 text-xs text-gray-500">Identifiant unique de votre compte Nexaah</p>
                    </div>

                    <!-- Sender ID -->
                    <div>
                        <label for="nexaah_sender_id" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-signature text-purple-400 mr-1"></i> Sender ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nexaah_sender_id" id="nexaah_sender_id"
                               value="{{ old('nexaah_sender_id', $smsSettings['nexaah_sender_id']->value ?? 'ASSO') }}"
                               maxlength="11"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="ASSO">
                        <p class="mt-1 text-xs text-gray-500">Nom affiché comme expéditeur (max 11 caractères)</p>
                    </div>

                    <!-- Base URL -->
                    <div class="md:col-span-2">
                        <label for="nexaah_base_url" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-link text-purple-400 mr-1"></i> URL de l'API
                        </label>
                        <input type="url" name="nexaah_base_url" id="nexaah_base_url"
                               value="{{ old('nexaah_base_url', $smsSettings['nexaah_base_url']->value ?? 'https://api.nexaah.com/v1') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="https://api.nexaah.com/v1">
                        <p class="mt-1 text-xs text-gray-500">URL de base de l'API Nexaah</p>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-cogs text-purple-500 mr-2"></i>
                    Paramètres Avancés
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Default Country Code -->
                    <div>
                        <label for="nexaah_country_code" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-flag text-purple-400 mr-1"></i> Indicatif pays par défaut
                        </label>
                        <input type="text" name="nexaah_country_code" id="nexaah_country_code"
                               value="{{ old('nexaah_country_code', $smsSettings['nexaah_country_code']->value ?? '+229') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="+229">
                        <p class="mt-1 text-xs text-gray-500">Code international pour le Bénin</p>
                    </div>

                    <!-- Timeout -->
                    <div>
                        <label for="nexaah_timeout" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-clock text-purple-400 mr-1"></i> Timeout (secondes)
                        </label>
                        <input type="number" name="nexaah_timeout" id="nexaah_timeout"
                               value="{{ old('nexaah_timeout', $smsSettings['nexaah_timeout']->value ?? '30') }}"
                               min="10" max="120"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="30">
                        <p class="mt-1 text-xs text-gray-500">Délai d'attente des requêtes API</p>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-purple-500/10 border border-purple-500/30 rounded-xl p-6 mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-purple-500 text-2xl mt-1"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-base font-semibold text-purple-400 mb-2">Comment obtenir vos identifiants Nexaah ?</h4>
                        <ul class="text-sm text-purple-300/80 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-purple-500 mr-2 mt-0.5"></i>
                                <span>Créez un compte sur <a href="https://nexaah.com" target="_blank" class="underline hover:text-purple-300">nexaah.com</a></span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-purple-500 mr-2 mt-0.5"></i>
                                <span>Accédez à votre tableau de bord et récupérez vos clés API</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-purple-500 mr-2 mt-0.5"></i>
                                <span>Enregistrez un Sender ID pour identifier vos SMS</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-purple-500 mr-2 mt-0.5"></i>
                                <span>Rechargez votre compte pour commencer à envoyer des SMS</span>
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
                        class="px-8 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i> Enregistrer la configuration
                </button>
            </div>
        </form>
    </div>

    <!-- WhatsApp Business Tab -->
    <div x-show="activeService === 'whatsapp'" x-cloak>
        <form action="{{ route('admin.settings.services.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="service_type" value="whatsapp">

            <!-- Service Header Card -->
            <div class="bg-gradient-to-br from-green-500/10 to-green-600/5 rounded-xl shadow-lg border border-green-500/20 p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fab fa-whatsapp text-3xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">WhatsApp Business API</h3>
                            <p class="text-gray-400 mt-1">Envoyez des messages via l'API officielle WhatsApp Business</p>
                        </div>
                    </div>
                    <!-- Enable/Disable Toggle -->
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="whatsapp_enabled" value="1"
                               {{ old('whatsapp_enabled', $whatsappSettings['whatsapp_enabled']->value ?? '0') == '1' ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-8 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-500/20 rounded-full peer peer-checked:after:translate-x-8 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-green-500 peer-checked:to-green-600 shadow-inner"></div>
                        <span class="ml-3 text-sm font-medium text-gray-300">
                            <span x-show="$el.previousElementSibling.querySelector('input').checked" class="text-green-400">Activé</span>
                            <span x-show="!$el.previousElementSibling.querySelector('input').checked" class="text-gray-500">Désactivé</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Business Account Configuration -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-building text-green-500 mr-2"></i>
                    Compte WhatsApp Business
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Business Account ID -->
                    <div>
                        <label for="whatsapp_business_account_id" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-id-card text-green-400 mr-1"></i> Business Account ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="whatsapp_business_account_id" id="whatsapp_business_account_id"
                               value="{{ old('whatsapp_business_account_id', $whatsappSettings['whatsapp_business_account_id']->value ?? '') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="Ex: 123456789012345">
                        <p class="mt-1 text-xs text-gray-500">ID de votre compte WhatsApp Business</p>
                    </div>

                    <!-- Phone Number ID -->
                    <div>
                        <label for="whatsapp_phone_number_id" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-phone text-green-400 mr-1"></i> Phone Number ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="whatsapp_phone_number_id" id="whatsapp_phone_number_id"
                               value="{{ old('whatsapp_phone_number_id', $whatsappSettings['whatsapp_phone_number_id']->value ?? '') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="Ex: 987654321098765">
                        <p class="mt-1 text-xs text-gray-500">ID unique de votre numéro WhatsApp</p>
                    </div>

                    <!-- Business Phone -->
                    <div>
                        <label for="whatsapp_business_phone" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-mobile-alt text-green-400 mr-1"></i> Numéro WhatsApp Business <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="whatsapp_business_phone" id="whatsapp_business_phone"
                               value="{{ old('whatsapp_business_phone', $whatsappSettings['whatsapp_business_phone']->value ?? '') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="+229XXXXXXXXX">
                        <p class="mt-1 text-xs text-gray-500">Numéro au format international avec indicatif</p>
                    </div>

                    <!-- Display Name -->
                    <div>
                        <label for="whatsapp_display_name" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-tag text-green-400 mr-1"></i> Nom d'affichage
                        </label>
                        <input type="text" name="whatsapp_display_name" id="whatsapp_display_name"
                               value="{{ old('whatsapp_display_name', $whatsappSettings['whatsapp_display_name']->value ?? 'ASSO') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="ASSO">
                        <p class="mt-1 text-xs text-gray-500">Nom affiché dans les conversations</p>
                    </div>
                </div>
            </div>

            <!-- API Configuration -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
                <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
                    <i class="fas fa-key text-green-500 mr-2"></i>
                    Configuration API
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Access Token -->
                    <div class="md:col-span-2">
                        <label for="whatsapp_access_token" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-key text-green-400 mr-1"></i> Access Token (Permanent) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="whatsapp_access_token" id="whatsapp_access_token"
                                   value="{{ old('whatsapp_access_token', $whatsappSettings['whatsapp_access_token']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                   placeholder="Entrez votre token d'accès permanent">
                            <button type="button" onclick="togglePassword('whatsapp_access_token')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Token permanent généré depuis Meta Business Suite</p>
                    </div>

                    <!-- App ID -->
                    <div>
                        <label for="whatsapp_app_id" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-cube text-green-400 mr-1"></i> App ID
                        </label>
                        <input type="text" name="whatsapp_app_id" id="whatsapp_app_id"
                               value="{{ old('whatsapp_app_id', $whatsappSettings['whatsapp_app_id']->value ?? '') }}"
                               class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                               placeholder="Ex: 123456789012345">
                        <p class="mt-1 text-xs text-gray-500">ID de votre application Meta</p>
                    </div>

                    <!-- App Secret -->
                    <div>
                        <label for="whatsapp_app_secret" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-lock text-green-400 mr-1"></i> App Secret
                        </label>
                        <div class="relative">
                            <input type="password" name="whatsapp_app_secret" id="whatsapp_app_secret"
                                   value="{{ old('whatsapp_app_secret', $whatsappSettings['whatsapp_app_secret']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                   placeholder="Entrez le secret de l'application">
                            <button type="button" onclick="togglePassword('whatsapp_app_secret')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Clé secrète de votre application</p>
                    </div>

                    <!-- Graph API Version -->
                    <div>
                        <label for="whatsapp_api_version" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-code-branch text-green-400 mr-1"></i> Version de l'API Graph
                        </label>
                        <select name="whatsapp_api_version" id="whatsapp_api_version"
                                class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="v18.0" {{ old('whatsapp_api_version', $whatsappSettings['whatsapp_api_version']->value ?? 'v18.0') == 'v18.0' ? 'selected' : '' }}>v18.0</option>
                            <option value="v19.0" {{ old('whatsapp_api_version', $whatsappSettings['whatsapp_api_version']->value ?? '') == 'v19.0' ? 'selected' : '' }}>v19.0</option>
                            <option value="v20.0" {{ old('whatsapp_api_version', $whatsappSettings['whatsapp_api_version']->value ?? '') == 'v20.0' ? 'selected' : '' }}>v20.0</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Version de l'API Graph Facebook/Meta</p>
                    </div>

                    <!-- Webhook Verify Token -->
                    <div>
                        <label for="whatsapp_webhook_verify_token" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-shield-alt text-green-400 mr-1"></i> Webhook Verify Token
                        </label>
                        <div class="relative">
                            <input type="password" name="whatsapp_webhook_verify_token" id="whatsapp_webhook_verify_token"
                                   value="{{ old('whatsapp_webhook_verify_token', $whatsappSettings['whatsapp_webhook_verify_token']->value ?? '') }}"
                                   class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                   placeholder="Token de vérification webhook">
                            <button type="button" onclick="togglePassword('whatsapp_webhook_verify_token')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Token pour valider les webhooks entrants</p>
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
                        <h4 class="text-base font-semibold text-green-400 mb-2">Configuration de WhatsApp Business API</h4>
                        <ul class="text-sm text-green-300/80 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Créez une application sur <a href="https://developers.facebook.com" target="_blank" class="underline hover:text-green-300">Meta for Developers</a></span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Activez l'API WhatsApp Business dans votre application</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Configurez un numéro de téléphone vérifié pour WhatsApp Business</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Générez un token d'accès permanent depuis Meta Business Suite</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                <span>Configurez les webhooks pour recevoir les messages entrants</span>
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
