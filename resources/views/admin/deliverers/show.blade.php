@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.deliverers.index') }}" class="text-gray-400 hover:text-primary-500 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-white">Profil Livreur Partenaire</h1>
                    <p class="text-gray-400">{{ $deliverer->company_name }}</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.deliverers.edit', $deliverer) }}"
                   class="px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <form action="{{ route('admin.deliverers.destroy', $deliverer) }}" method="POST"
                      onsubmit="return confirm('Supprimer ce livreur partenaire ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                        <i class="fas fa-trash mr-2"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Company & Profile Card -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Company Card -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <div class="flex flex-col items-center mb-6">
                    @if($deliverer->company_logo)
                        <img src="{{ Storage::url($deliverer->company_logo) }}" alt="{{ $deliverer->company_name }}"
                             class="w-28 h-28 rounded-xl object-cover border-2 border-dark-300 mb-4 shadow-lg">
                    @else
                        <div class="w-28 h-28 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-4xl font-bold mb-4 shadow-lg">
                            {{ strtoupper(substr($deliverer->company_name ?? 'L', 0, 2)) }}
                        </div>
                    @endif
                    <h2 class="text-xl font-bold text-white text-center">{{ $deliverer->company_name ?? 'Sans entreprise' }}</h2>
                    <span class="mt-2 px-4 py-1 inline-flex text-sm font-semibold rounded-full bg-primary-500/20 text-primary-300 border border-primary-500/50">
                        <i class="fas fa-truck mr-2"></i> Livreur Partenaire
                    </span>
                </div>

                <div class="border-t border-dark-200 pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400"><i class="fas fa-globe text-primary-500 mr-2"></i> Pays</span>
                        <span class="font-medium text-white">{{ $deliverer->country ?? 'Non renseigné' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400"><i class="fas fa-calendar text-primary-500 mr-2"></i> Inscrit</span>
                        <span class="font-medium text-white">{{ $deliverer->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Agent Profile -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-user text-primary-500 mr-2"></i> Agent de livraison
                </h3>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-xl font-bold shadow-lg">
                        {{ strtoupper(substr($deliverer->first_name, 0, 1)) }}{{ strtoupper(substr($deliverer->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-white font-semibold text-lg">{{ $deliverer->name }}</p>
                        <p class="text-gray-400 text-sm">
                            @if($deliverer->gender == 'male')
                                <i class="fas fa-mars text-blue-400 mr-1"></i> Homme
                            @elseif($deliverer->gender == 'female')
                                <i class="fas fa-venus text-pink-400 mr-1"></i> Femme
                            @else
                                Non renseigné
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contact Info -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-address-card text-primary-500 mr-2"></i>
                    Coordonnées
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm text-gray-500">Prénom</label>
                        <p class="text-white font-medium">{{ $deliverer->first_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Nom</label>
                        <p class="text-white font-medium">{{ $deliverer->last_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Email</label>
                        <p class="text-white font-medium">
                            <i class="fas fa-envelope text-primary-500 mr-1"></i>
                            {{ $deliverer->email }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Téléphone</label>
                        <p class="text-white font-medium">
                            @if($deliverer->phone)
                                <i class="fas fa-phone text-primary-500 mr-1"></i>
                                {{ $deliverer->phone }}
                            @else
                                <span class="text-gray-500">Non renseigné</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Date de naissance</label>
                        <p class="text-white font-medium">
                            {{ $deliverer->birth_date ? $deliverer->birth_date->format('d/m/Y') : 'Non renseigné' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Pays</label>
                        <p class="text-white font-medium">{{ $deliverer->country ?? 'Non renseigné' }}</p>
                    </div>
                </div>
            </div>

            <!-- Entreprise Details -->
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-building text-primary-500 mr-2"></i>
                    Entreprise
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm text-gray-500">Nom de l'entreprise</label>
                        <p class="text-white font-medium text-lg">{{ $deliverer->company_name ?? 'Non renseigné' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Logo</label>
                        @if($deliverer->company_logo)
                            <div class="mt-1">
                                <img src="{{ Storage::url($deliverer->company_logo) }}" alt="{{ $deliverer->company_name }}"
                                     class="w-20 h-20 rounded-lg object-cover border border-dark-300">
                            </div>
                        @else
                            <p class="text-gray-500">Aucun logo</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Map Section -->
            @if($deliverer->latitude && $deliverer->longitude)
                <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                        Localisation
                    </h3>
                    @include('admin.partials.google-map-view', [
                        'id' => 'deliverer-location-map',
                        'label' => 'Localisation du livreur',
                        'latitude' => $deliverer->latitude,
                        'longitude' => $deliverer->longitude,
                        'address' => $deliverer->address,
                        'zoom' => 15
                    ])
                </div>
            @elseif($deliverer->address)
                <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                        Adresse
                    </h3>
                    <p class="text-white">{{ $deliverer->address }}</p>
                </div>
            @endif

            <!-- Referral Info -->
            @if($deliverer->referral_code)
                <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-link text-primary-500 mr-2"></i>
                        Code Parrainage
                    </h3>
                    <code class="px-4 py-2 bg-primary-500/20 text-primary-300 rounded-lg border border-primary-500/50 font-mono text-lg">
                        {{ $deliverer->referral_code }}
                    </code>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
