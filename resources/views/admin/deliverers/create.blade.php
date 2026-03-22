@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin.deliverers.index') }}" class="text-gray-400 hover:text-primary-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold text-white">Ajouter un Livreur Partenaire</h1>
        </div>
        <p class="text-gray-400 ml-10">Enregistrez un nouveau partenaire de livraison avec les informations de son entreprise</p>
    </div>

    <form action="{{ route('admin.deliverers.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Company Section -->
        <div class="bg-dark-100 rounded-xl shadow-lg p-6 mb-6 border border-dark-200">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-building text-primary-500 mr-2"></i>
                Entreprise de livraison
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-building text-primary-500 mr-1"></i>
                        Nom de l'entreprise <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="company_name" id="company_name" value="{{ old('company_name') }}" required
                           placeholder="Ex: DHL Express, Sogel Bénin..."
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('company_name') border-red-500 @enderror">
                    @error('company_name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Company Logo -->
                <div>
                    <label for="company_logo" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-image text-primary-500 mr-1"></i>
                        Logo de l'entreprise
                    </label>
                    <input type="file" name="company_logo" id="company_logo" accept="image/*"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-500 file:text-white hover:file:bg-primary-600 @error('company_logo') border-red-500 @enderror">
                    @error('company_logo')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">Formats: JPEG, PNG, JPG, GIF, SVG, WEBP. Max: 2 Mo</p>
                </div>
            </div>
        </div>

        <!-- Personal Info Section -->
        <div class="bg-dark-100 rounded-xl shadow-lg p-6 mb-6 border border-dark-200">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-user text-primary-500 mr-2"></i>
                Informations personnelles du livreur
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-white mb-2">
                        Prénom <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('first_name') border-red-500 @enderror">
                    @error('first_name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-white mb-2">
                        Nom <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('last_name') border-red-500 @enderror">
                    @error('last_name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-envelope text-primary-500 mr-1"></i>
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('email') border-red-500 @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-phone text-primary-500 mr-1"></i>
                        Téléphone
                    </label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="+229 XX XX XX XX"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('phone') border-red-500 @enderror">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock text-primary-500 mr-1"></i>
                        Mot de passe <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('password') border-red-500 @enderror">
                    @error('password')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">Minimum 8 caractères</p>
                </div>

                <!-- Password Confirmation -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock text-primary-500 mr-1"></i>
                        Confirmer le mot de passe <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-venus-mars text-primary-500 mr-1"></i>
                        Genre
                    </label>
                    <select name="gender" id="gender"
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Sélectionnez</option>
                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Homme</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Femme</option>
                        <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Autre</option>
                    </select>
                </div>

                <!-- Birth Date -->
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-calendar text-primary-500 mr-1"></i>
                        Date de naissance
                    </label>
                    <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date') }}"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Country -->
                <div class="md:col-span-2">
                    <label for="country" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-globe text-primary-500 mr-1"></i>
                        Pays
                    </label>
                    <select name="country" id="country"
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Sélectionnez un pays</option>
                        @foreach([
                            'Afghanistan', 'Afrique du Sud', 'Albanie', 'Algérie', 'Allemagne', 'Andorre', 'Angola', 'Arabie Saoudite',
                            'Argentine', 'Arménie', 'Australie', 'Autriche', 'Azerbaïdjan', 'Bahamas', 'Bahreïn', 'Bangladesh',
                            'Belgique', 'Bénin', 'Bhoutan', 'Bolivie', 'Bosnie-Herzégovine', 'Botswana', 'Brésil', 'Brunei',
                            'Bulgarie', 'Burkina Faso', 'Burundi', 'Cambodge', 'Cameroun', 'Canada', 'Cap-Vert', 'Chili', 'Chine',
                            'Chypre', 'Colombie', 'Comores', 'Congo', 'Corée du Nord', 'Corée du Sud', 'Costa Rica', 'Côte d\'Ivoire',
                            'Croatie', 'Cuba', 'Danemark', 'Djibouti', 'Égypte', 'Émirats arabes unis', 'Équateur', 'Érythrée',
                            'Espagne', 'Estonie', 'États-Unis', 'Éthiopie', 'Finlande', 'France', 'Gabon', 'Gambie', 'Géorgie',
                            'Ghana', 'Grèce', 'Guatemala', 'Guinée', 'Guinée équatoriale', 'Guinée-Bissau', 'Guyana', 'Haïti',
                            'Honduras', 'Hongrie', 'Inde', 'Indonésie', 'Irak', 'Iran', 'Irlande', 'Islande', 'Israël', 'Italie',
                            'Jamaïque', 'Japon', 'Jordanie', 'Kazakhstan', 'Kenya', 'Kirghizistan', 'Koweït', 'Laos', 'Lesotho',
                            'Lettonie', 'Liban', 'Libéria', 'Libye', 'Liechtenstein', 'Lituanie', 'Luxembourg', 'Macédoine du Nord',
                            'Madagascar', 'Malaisie', 'Malawi', 'Maldives', 'Mali', 'Malte', 'Maroc', 'Maurice', 'Mauritanie',
                            'Mexique', 'Moldavie', 'Monaco', 'Mongolie', 'Monténégro', 'Mozambique', 'Myanmar', 'Namibie', 'Népal',
                            'Nicaragua', 'Niger', 'Nigeria', 'Norvège', 'Nouvelle-Zélande', 'Oman', 'Ouganda', 'Ouzbékistan',
                            'Pakistan', 'Palestine', 'Panama', 'Papouasie-Nouvelle-Guinée', 'Paraguay', 'Pays-Bas', 'Pérou',
                            'Philippines', 'Pologne', 'Portugal', 'Qatar', 'République centrafricaine', 'République démocratique du Congo',
                            'République dominicaine', 'République tchèque', 'Roumanie', 'Royaume-Uni', 'Russie', 'Rwanda', 'Saint-Marin',
                            'Salvador', 'Sénégal', 'Serbie', 'Seychelles', 'Sierra Leone', 'Singapour', 'Slovaquie', 'Slovénie',
                            'Somalie', 'Soudan', 'Soudan du Sud', 'Sri Lanka', 'Suède', 'Suisse', 'Suriname', 'Syrie', 'Tadjikistan',
                            'Tanzanie', 'Tchad', 'Thaïlande', 'Timor oriental', 'Togo', 'Trinité-et-Tobago', 'Tunisie', 'Turkménistan',
                            'Turquie', 'Ukraine', 'Uruguay', 'Vanuatu', 'Vatican', 'Venezuela', 'Viêt Nam', 'Yémen', 'Zambie', 'Zimbabwe'
                        ] as $c)
                            <option value="{{ $c }}" {{ old('country', 'Bénin') == $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Location Section -->
        <div class="bg-dark-100 rounded-xl shadow-lg p-6 mb-6 border border-dark-200">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                Localisation
            </h3>
            @include('admin.partials.google-map', [
                'id' => 'deliverer-map',
                'label' => 'Localisation du livreur',
                'latitude' => old('latitude'),
                'longitude' => old('longitude'),
                'address' => old('address'),
                'zoom' => 13
            ])
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4">
            <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all duration-200 shadow-lg hover:shadow-xl">
                <i class="fas fa-save mr-2"></i> Créer le livreur partenaire
            </button>
            <a href="{{ route('admin.deliverers.index') }}"
               class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all">
                <i class="fas fa-times mr-2"></i> Annuler
            </a>
        </div>
    </form>
</div>
@endsection
