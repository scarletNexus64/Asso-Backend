@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin.users.index') }}"
               class="text-gray-400 hover:text-primary-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold text-white">Créer un Utilisateur</h1>
        </div>
        <p class="text-gray-400 ml-10">Ajoutez un nouvel utilisateur à la plateforme</p>
    </div>

    <!-- Form -->
    <div class="bg-dark-100 rounded-xl shadow-lg p-6">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user text-primary-500 mr-1"></i>
                        Prénom <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="first_name"
                           id="first_name"
                           value="{{ old('first_name') }}"
                           required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('first_name') border-red-500 @enderror">
                    @error('first_name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user text-primary-500 mr-1"></i>
                        Nom <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="last_name"
                           id="last_name"
                           value="{{ old('last_name') }}"
                           required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('last_name') border-red-500 @enderror">
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
                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('email') border-red-500 @enderror">
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
                    <input type="text"
                           name="phone"
                           id="phone"
                           value="{{ old('phone') }}"
                           placeholder="+229 XX XX XX XX"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('phone') border-red-500 @enderror">
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
                    <input type="password"
                           name="password"
                           id="password"
                           required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('password') border-red-500 @enderror">
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
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           required
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user-tag text-primary-500 mr-1"></i>
                        Rôle <span class="text-red-500">*</span>
                    </label>
                    <select name="role"
                            id="role"
                            required
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('role') border-red-500 @enderror">
                        <option value="">Sélectionnez un rôle</option>
                        <option value="client" {{ old('role') == 'client' ? 'selected' : '' }}>Client</option>
                        <option value="vendeur" {{ old('role') == 'vendeur' ? 'selected' : '' }}>Vendeur</option>
                        <option value="livreur" {{ old('role') == 'livreur' ? 'selected' : '' }}>Livreur</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-venus-mars text-primary-500 mr-1"></i>
                        Genre
                    </label>
                    <select name="gender"
                            id="gender"
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('gender') border-red-500 @enderror">
                        <option value="">Sélectionnez un genre</option>
                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Homme</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Femme</option>
                        <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Autre</option>
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Birth Date -->
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-calendar text-primary-500 mr-1"></i>
                        Date de naissance
                    </label>
                    <input type="date"
                           name="birth_date"
                           id="birth_date"
                           value="{{ old('birth_date') }}"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('birth_date') border-red-500 @enderror">
                    @error('birth_date')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Country -->
                <div>
                    <label for="country" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-globe text-primary-500 mr-1"></i>
                        Pays
                    </label>
                    <select name="country"
                            id="country"
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('country') border-red-500 @enderror">
                        <option value="">Sélectionnez un pays</option>
                        <option value="Afghanistan" {{ old('country') == 'Afghanistan' ? 'selected' : '' }}>Afghanistan</option>
                        <option value="Afrique du Sud" {{ old('country') == 'Afrique du Sud' ? 'selected' : '' }}>Afrique du Sud</option>
                        <option value="Albanie" {{ old('country') == 'Albanie' ? 'selected' : '' }}>Albanie</option>
                        <option value="Algérie" {{ old('country') == 'Algérie' ? 'selected' : '' }}>Algérie</option>
                        <option value="Allemagne" {{ old('country') == 'Allemagne' ? 'selected' : '' }}>Allemagne</option>
                        <option value="Andorre" {{ old('country') == 'Andorre' ? 'selected' : '' }}>Andorre</option>
                        <option value="Angola" {{ old('country') == 'Angola' ? 'selected' : '' }}>Angola</option>
                        <option value="Arabie Saoudite" {{ old('country') == 'Arabie Saoudite' ? 'selected' : '' }}>Arabie Saoudite</option>
                        <option value="Argentine" {{ old('country') == 'Argentine' ? 'selected' : '' }}>Argentine</option>
                        <option value="Arménie" {{ old('country') == 'Arménie' ? 'selected' : '' }}>Arménie</option>
                        <option value="Australie" {{ old('country') == 'Australie' ? 'selected' : '' }}>Australie</option>
                        <option value="Autriche" {{ old('country') == 'Autriche' ? 'selected' : '' }}>Autriche</option>
                        <option value="Azerbaïdjan" {{ old('country') == 'Azerbaïdjan' ? 'selected' : '' }}>Azerbaïdjan</option>
                        <option value="Bahamas" {{ old('country') == 'Bahamas' ? 'selected' : '' }}>Bahamas</option>
                        <option value="Bahreïn" {{ old('country') == 'Bahreïn' ? 'selected' : '' }}>Bahreïn</option>
                        <option value="Bangladesh" {{ old('country') == 'Bangladesh' ? 'selected' : '' }}>Bangladesh</option>
                        <option value="Belgique" {{ old('country') == 'Belgique' ? 'selected' : '' }}>Belgique</option>
                        <option value="Bénin" {{ old('country', 'Bénin') == 'Bénin' ? 'selected' : '' }}>Bénin</option>
                        <option value="Bhoutan" {{ old('country') == 'Bhoutan' ? 'selected' : '' }}>Bhoutan</option>
                        <option value="Bolivie" {{ old('country') == 'Bolivie' ? 'selected' : '' }}>Bolivie</option>
                        <option value="Bosnie-Herzégovine" {{ old('country') == 'Bosnie-Herzégovine' ? 'selected' : '' }}>Bosnie-Herzégovine</option>
                        <option value="Botswana" {{ old('country') == 'Botswana' ? 'selected' : '' }}>Botswana</option>
                        <option value="Brésil" {{ old('country') == 'Brésil' ? 'selected' : '' }}>Brésil</option>
                        <option value="Brunei" {{ old('country') == 'Brunei' ? 'selected' : '' }}>Brunei</option>
                        <option value="Bulgarie" {{ old('country') == 'Bulgarie' ? 'selected' : '' }}>Bulgarie</option>
                        <option value="Burkina Faso" {{ old('country') == 'Burkina Faso' ? 'selected' : '' }}>Burkina Faso</option>
                        <option value="Burundi" {{ old('country') == 'Burundi' ? 'selected' : '' }}>Burundi</option>
                        <option value="Cambodge" {{ old('country') == 'Cambodge' ? 'selected' : '' }}>Cambodge</option>
                        <option value="Cameroun" {{ old('country') == 'Cameroun' ? 'selected' : '' }}>Cameroun</option>
                        <option value="Canada" {{ old('country') == 'Canada' ? 'selected' : '' }}>Canada</option>
                        <option value="Cap-Vert" {{ old('country') == 'Cap-Vert' ? 'selected' : '' }}>Cap-Vert</option>
                        <option value="Chili" {{ old('country') == 'Chili' ? 'selected' : '' }}>Chili</option>
                        <option value="Chine" {{ old('country') == 'Chine' ? 'selected' : '' }}>Chine</option>
                        <option value="Chypre" {{ old('country') == 'Chypre' ? 'selected' : '' }}>Chypre</option>
                        <option value="Colombie" {{ old('country') == 'Colombie' ? 'selected' : '' }}>Colombie</option>
                        <option value="Comores" {{ old('country') == 'Comores' ? 'selected' : '' }}>Comores</option>
                        <option value="Congo" {{ old('country') == 'Congo' ? 'selected' : '' }}>Congo</option>
                        <option value="Corée du Nord" {{ old('country') == 'Corée du Nord' ? 'selected' : '' }}>Corée du Nord</option>
                        <option value="Corée du Sud" {{ old('country') == 'Corée du Sud' ? 'selected' : '' }}>Corée du Sud</option>
                        <option value="Costa Rica" {{ old('country') == 'Costa Rica' ? 'selected' : '' }}>Costa Rica</option>
                        <option value="Côte d'Ivoire" {{ old('country') == "Côte d'Ivoire" ? 'selected' : '' }}>Côte d'Ivoire</option>
                        <option value="Croatie" {{ old('country') == 'Croatie' ? 'selected' : '' }}>Croatie</option>
                        <option value="Cuba" {{ old('country') == 'Cuba' ? 'selected' : '' }}>Cuba</option>
                        <option value="Danemark" {{ old('country') == 'Danemark' ? 'selected' : '' }}>Danemark</option>
                        <option value="Djibouti" {{ old('country') == 'Djibouti' ? 'selected' : '' }}>Djibouti</option>
                        <option value="Égypte" {{ old('country') == 'Égypte' ? 'selected' : '' }}>Égypte</option>
                        <option value="Émirats arabes unis" {{ old('country') == 'Émirats arabes unis' ? 'selected' : '' }}>Émirats arabes unis</option>
                        <option value="Équateur" {{ old('country') == 'Équateur' ? 'selected' : '' }}>Équateur</option>
                        <option value="Érythrée" {{ old('country') == 'Érythrée' ? 'selected' : '' }}>Érythrée</option>
                        <option value="Espagne" {{ old('country') == 'Espagne' ? 'selected' : '' }}>Espagne</option>
                        <option value="Estonie" {{ old('country') == 'Estonie' ? 'selected' : '' }}>Estonie</option>
                        <option value="États-Unis" {{ old('country') == 'États-Unis' ? 'selected' : '' }}>États-Unis</option>
                        <option value="Éthiopie" {{ old('country') == 'Éthiopie' ? 'selected' : '' }}>Éthiopie</option>
                        <option value="Finlande" {{ old('country') == 'Finlande' ? 'selected' : '' }}>Finlande</option>
                        <option value="France" {{ old('country') == 'France' ? 'selected' : '' }}>France</option>
                        <option value="Gabon" {{ old('country') == 'Gabon' ? 'selected' : '' }}>Gabon</option>
                        <option value="Gambie" {{ old('country') == 'Gambie' ? 'selected' : '' }}>Gambie</option>
                        <option value="Géorgie" {{ old('country') == 'Géorgie' ? 'selected' : '' }}>Géorgie</option>
                        <option value="Ghana" {{ old('country') == 'Ghana' ? 'selected' : '' }}>Ghana</option>
                        <option value="Grèce" {{ old('country') == 'Grèce' ? 'selected' : '' }}>Grèce</option>
                        <option value="Guatemala" {{ old('country') == 'Guatemala' ? 'selected' : '' }}>Guatemala</option>
                        <option value="Guinée" {{ old('country') == 'Guinée' ? 'selected' : '' }}>Guinée</option>
                        <option value="Guinée équatoriale" {{ old('country') == 'Guinée équatoriale' ? 'selected' : '' }}>Guinée équatoriale</option>
                        <option value="Guinée-Bissau" {{ old('country') == 'Guinée-Bissau' ? 'selected' : '' }}>Guinée-Bissau</option>
                        <option value="Guyana" {{ old('country') == 'Guyana' ? 'selected' : '' }}>Guyana</option>
                        <option value="Haïti" {{ old('country') == 'Haïti' ? 'selected' : '' }}>Haïti</option>
                        <option value="Honduras" {{ old('country') == 'Honduras' ? 'selected' : '' }}>Honduras</option>
                        <option value="Hongrie" {{ old('country') == 'Hongrie' ? 'selected' : '' }}>Hongrie</option>
                        <option value="Inde" {{ old('country') == 'Inde' ? 'selected' : '' }}>Inde</option>
                        <option value="Indonésie" {{ old('country') == 'Indonésie' ? 'selected' : '' }}>Indonésie</option>
                        <option value="Irak" {{ old('country') == 'Irak' ? 'selected' : '' }}>Irak</option>
                        <option value="Iran" {{ old('country') == 'Iran' ? 'selected' : '' }}>Iran</option>
                        <option value="Irlande" {{ old('country') == 'Irlande' ? 'selected' : '' }}>Irlande</option>
                        <option value="Islande" {{ old('country') == 'Islande' ? 'selected' : '' }}>Islande</option>
                        <option value="Israël" {{ old('country') == 'Israël' ? 'selected' : '' }}>Israël</option>
                        <option value="Italie" {{ old('country') == 'Italie' ? 'selected' : '' }}>Italie</option>
                        <option value="Jamaïque" {{ old('country') == 'Jamaïque' ? 'selected' : '' }}>Jamaïque</option>
                        <option value="Japon" {{ old('country') == 'Japon' ? 'selected' : '' }}>Japon</option>
                        <option value="Jordanie" {{ old('country') == 'Jordanie' ? 'selected' : '' }}>Jordanie</option>
                        <option value="Kazakhstan" {{ old('country') == 'Kazakhstan' ? 'selected' : '' }}>Kazakhstan</option>
                        <option value="Kenya" {{ old('country') == 'Kenya' ? 'selected' : '' }}>Kenya</option>
                        <option value="Kirghizistan" {{ old('country') == 'Kirghizistan' ? 'selected' : '' }}>Kirghizistan</option>
                        <option value="Koweït" {{ old('country') == 'Koweït' ? 'selected' : '' }}>Koweït</option>
                        <option value="Laos" {{ old('country') == 'Laos' ? 'selected' : '' }}>Laos</option>
                        <option value="Lesotho" {{ old('country') == 'Lesotho' ? 'selected' : '' }}>Lesotho</option>
                        <option value="Lettonie" {{ old('country') == 'Lettonie' ? 'selected' : '' }}>Lettonie</option>
                        <option value="Liban" {{ old('country') == 'Liban' ? 'selected' : '' }}>Liban</option>
                        <option value="Libéria" {{ old('country') == 'Libéria' ? 'selected' : '' }}>Libéria</option>
                        <option value="Libye" {{ old('country') == 'Libye' ? 'selected' : '' }}>Libye</option>
                        <option value="Liechtenstein" {{ old('country') == 'Liechtenstein' ? 'selected' : '' }}>Liechtenstein</option>
                        <option value="Lituanie" {{ old('country') == 'Lituanie' ? 'selected' : '' }}>Lituanie</option>
                        <option value="Luxembourg" {{ old('country') == 'Luxembourg' ? 'selected' : '' }}>Luxembourg</option>
                        <option value="Macédoine du Nord" {{ old('country') == 'Macédoine du Nord' ? 'selected' : '' }}>Macédoine du Nord</option>
                        <option value="Madagascar" {{ old('country') == 'Madagascar' ? 'selected' : '' }}>Madagascar</option>
                        <option value="Malaisie" {{ old('country') == 'Malaisie' ? 'selected' : '' }}>Malaisie</option>
                        <option value="Malawi" {{ old('country') == 'Malawi' ? 'selected' : '' }}>Malawi</option>
                        <option value="Maldives" {{ old('country') == 'Maldives' ? 'selected' : '' }}>Maldives</option>
                        <option value="Mali" {{ old('country') == 'Mali' ? 'selected' : '' }}>Mali</option>
                        <option value="Malte" {{ old('country') == 'Malte' ? 'selected' : '' }}>Malte</option>
                        <option value="Maroc" {{ old('country') == 'Maroc' ? 'selected' : '' }}>Maroc</option>
                        <option value="Maurice" {{ old('country') == 'Maurice' ? 'selected' : '' }}>Maurice</option>
                        <option value="Mauritanie" {{ old('country') == 'Mauritanie' ? 'selected' : '' }}>Mauritanie</option>
                        <option value="Mexique" {{ old('country') == 'Mexique' ? 'selected' : '' }}>Mexique</option>
                        <option value="Moldavie" {{ old('country') == 'Moldavie' ? 'selected' : '' }}>Moldavie</option>
                        <option value="Monaco" {{ old('country') == 'Monaco' ? 'selected' : '' }}>Monaco</option>
                        <option value="Mongolie" {{ old('country') == 'Mongolie' ? 'selected' : '' }}>Mongolie</option>
                        <option value="Monténégro" {{ old('country') == 'Monténégro' ? 'selected' : '' }}>Monténégro</option>
                        <option value="Mozambique" {{ old('country') == 'Mozambique' ? 'selected' : '' }}>Mozambique</option>
                        <option value="Myanmar" {{ old('country') == 'Myanmar' ? 'selected' : '' }}>Myanmar</option>
                        <option value="Namibie" {{ old('country') == 'Namibie' ? 'selected' : '' }}>Namibie</option>
                        <option value="Népal" {{ old('country') == 'Népal' ? 'selected' : '' }}>Népal</option>
                        <option value="Nicaragua" {{ old('country') == 'Nicaragua' ? 'selected' : '' }}>Nicaragua</option>
                        <option value="Niger" {{ old('country') == 'Niger' ? 'selected' : '' }}>Niger</option>
                        <option value="Nigeria" {{ old('country') == 'Nigeria' ? 'selected' : '' }}>Nigeria</option>
                        <option value="Norvège" {{ old('country') == 'Norvège' ? 'selected' : '' }}>Norvège</option>
                        <option value="Nouvelle-Zélande" {{ old('country') == 'Nouvelle-Zélande' ? 'selected' : '' }}>Nouvelle-Zélande</option>
                        <option value="Oman" {{ old('country') == 'Oman' ? 'selected' : '' }}>Oman</option>
                        <option value="Ouganda" {{ old('country') == 'Ouganda' ? 'selected' : '' }}>Ouganda</option>
                        <option value="Ouzbékistan" {{ old('country') == 'Ouzbékistan' ? 'selected' : '' }}>Ouzbékistan</option>
                        <option value="Pakistan" {{ old('country') == 'Pakistan' ? 'selected' : '' }}>Pakistan</option>
                        <option value="Palestine" {{ old('country') == 'Palestine' ? 'selected' : '' }}>Palestine</option>
                        <option value="Panama" {{ old('country') == 'Panama' ? 'selected' : '' }}>Panama</option>
                        <option value="Papouasie-Nouvelle-Guinée" {{ old('country') == 'Papouasie-Nouvelle-Guinée' ? 'selected' : '' }}>Papouasie-Nouvelle-Guinée</option>
                        <option value="Paraguay" {{ old('country') == 'Paraguay' ? 'selected' : '' }}>Paraguay</option>
                        <option value="Pays-Bas" {{ old('country') == 'Pays-Bas' ? 'selected' : '' }}>Pays-Bas</option>
                        <option value="Pérou" {{ old('country') == 'Pérou' ? 'selected' : '' }}>Pérou</option>
                        <option value="Philippines" {{ old('country') == 'Philippines' ? 'selected' : '' }}>Philippines</option>
                        <option value="Pologne" {{ old('country') == 'Pologne' ? 'selected' : '' }}>Pologne</option>
                        <option value="Portugal" {{ old('country') == 'Portugal' ? 'selected' : '' }}>Portugal</option>
                        <option value="Qatar" {{ old('country') == 'Qatar' ? 'selected' : '' }}>Qatar</option>
                        <option value="République centrafricaine" {{ old('country') == 'République centrafricaine' ? 'selected' : '' }}>République centrafricaine</option>
                        <option value="République démocratique du Congo" {{ old('country') == 'République démocratique du Congo' ? 'selected' : '' }}>République démocratique du Congo</option>
                        <option value="République dominicaine" {{ old('country') == 'République dominicaine' ? 'selected' : '' }}>République dominicaine</option>
                        <option value="République tchèque" {{ old('country') == 'République tchèque' ? 'selected' : '' }}>République tchèque</option>
                        <option value="Roumanie" {{ old('country') == 'Roumanie' ? 'selected' : '' }}>Roumanie</option>
                        <option value="Royaume-Uni" {{ old('country') == 'Royaume-Uni' ? 'selected' : '' }}>Royaume-Uni</option>
                        <option value="Russie" {{ old('country') == 'Russie' ? 'selected' : '' }}>Russie</option>
                        <option value="Rwanda" {{ old('country') == 'Rwanda' ? 'selected' : '' }}>Rwanda</option>
                        <option value="Saint-Marin" {{ old('country') == 'Saint-Marin' ? 'selected' : '' }}>Saint-Marin</option>
                        <option value="Salvador" {{ old('country') == 'Salvador' ? 'selected' : '' }}>Salvador</option>
                        <option value="Sénégal" {{ old('country') == 'Sénégal' ? 'selected' : '' }}>Sénégal</option>
                        <option value="Serbie" {{ old('country') == 'Serbie' ? 'selected' : '' }}>Serbie</option>
                        <option value="Seychelles" {{ old('country') == 'Seychelles' ? 'selected' : '' }}>Seychelles</option>
                        <option value="Sierra Leone" {{ old('country') == 'Sierra Leone' ? 'selected' : '' }}>Sierra Leone</option>
                        <option value="Singapour" {{ old('country') == 'Singapour' ? 'selected' : '' }}>Singapour</option>
                        <option value="Slovaquie" {{ old('country') == 'Slovaquie' ? 'selected' : '' }}>Slovaquie</option>
                        <option value="Slovénie" {{ old('country') == 'Slovénie' ? 'selected' : '' }}>Slovénie</option>
                        <option value="Somalie" {{ old('country') == 'Somalie' ? 'selected' : '' }}>Somalie</option>
                        <option value="Soudan" {{ old('country') == 'Soudan' ? 'selected' : '' }}>Soudan</option>
                        <option value="Soudan du Sud" {{ old('country') == 'Soudan du Sud' ? 'selected' : '' }}>Soudan du Sud</option>
                        <option value="Sri Lanka" {{ old('country') == 'Sri Lanka' ? 'selected' : '' }}>Sri Lanka</option>
                        <option value="Suède" {{ old('country') == 'Suède' ? 'selected' : '' }}>Suède</option>
                        <option value="Suisse" {{ old('country') == 'Suisse' ? 'selected' : '' }}>Suisse</option>
                        <option value="Suriname" {{ old('country') == 'Suriname' ? 'selected' : '' }}>Suriname</option>
                        <option value="Syrie" {{ old('country') == 'Syrie' ? 'selected' : '' }}>Syrie</option>
                        <option value="Tadjikistan" {{ old('country') == 'Tadjikistan' ? 'selected' : '' }}>Tadjikistan</option>
                        <option value="Tanzanie" {{ old('country') == 'Tanzanie' ? 'selected' : '' }}>Tanzanie</option>
                        <option value="Tchad" {{ old('country') == 'Tchad' ? 'selected' : '' }}>Tchad</option>
                        <option value="Thaïlande" {{ old('country') == 'Thaïlande' ? 'selected' : '' }}>Thaïlande</option>
                        <option value="Timor oriental" {{ old('country') == 'Timor oriental' ? 'selected' : '' }}>Timor oriental</option>
                        <option value="Togo" {{ old('country') == 'Togo' ? 'selected' : '' }}>Togo</option>
                        <option value="Trinité-et-Tobago" {{ old('country') == 'Trinité-et-Tobago' ? 'selected' : '' }}>Trinité-et-Tobago</option>
                        <option value="Tunisie" {{ old('country') == 'Tunisie' ? 'selected' : '' }}>Tunisie</option>
                        <option value="Turkménistan" {{ old('country') == 'Turkménistan' ? 'selected' : '' }}>Turkménistan</option>
                        <option value="Turquie" {{ old('country') == 'Turquie' ? 'selected' : '' }}>Turquie</option>
                        <option value="Ukraine" {{ old('country') == 'Ukraine' ? 'selected' : '' }}>Ukraine</option>
                        <option value="Uruguay" {{ old('country') == 'Uruguay' ? 'selected' : '' }}>Uruguay</option>
                        <option value="Vanuatu" {{ old('country') == 'Vanuatu' ? 'selected' : '' }}>Vanuatu</option>
                        <option value="Vatican" {{ old('country') == 'Vatican' ? 'selected' : '' }}>Vatican</option>
                        <option value="Venezuela" {{ old('country') == 'Venezuela' ? 'selected' : '' }}>Venezuela</option>
                        <option value="Viêt Nam" {{ old('country') == 'Viêt Nam' ? 'selected' : '' }}>Viêt Nam</option>
                        <option value="Yémen" {{ old('country') == 'Yémen' ? 'selected' : '' }}>Yémen</option>
                        <option value="Zambie" {{ old('country') == 'Zambie' ? 'selected' : '' }}>Zambie</option>
                        <option value="Zimbabwe" {{ old('country') == 'Zimbabwe' ? 'selected' : '' }}>Zimbabwe</option>
                    </select>
                    @error('country')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Address with Google Maps -->
            <div class="mt-6">
                @include('admin.partials.google-map', [
                    'id' => 'user-map',
                    'label' => 'Localisation de l\'utilisateur',
                    'latitude' => old('latitude'),
                    'longitude' => old('longitude'),
                    'address' => old('address'),
                    'zoom' => 13
                ])
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex gap-4">
                <button type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>
                    Créer l'utilisateur
                </button>
                <a href="{{ route('admin.users.index') }}"
                   class="px-6 py-3 bg-gray-200 text-white rounded-lg hover:bg-gray-700 transition-all">
                    <i class="fas fa-times mr-2"></i>
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
