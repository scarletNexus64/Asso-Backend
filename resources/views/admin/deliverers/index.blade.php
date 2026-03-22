@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Livreurs Partenaires</h1>
            <p class="text-gray-400 mt-1">Gérez vos partenaires de livraison et leurs entreprises</p>
        </div>
        <a href="{{ route('admin.deliverers.create') }}"
           class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Nouveau Livreur
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-900/20 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-400">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-900/20 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-400">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-dark-100 rounded-xl shadow-lg p-6 mb-6 border border-dark-200">
        <form method="GET" action="{{ route('admin.deliverers.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-search text-primary-500 mr-1"></i> Recherche
                    </label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Nom, email, téléphone, entreprise..."
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-globe text-primary-500 mr-1"></i> Pays
                    </label>
                    <select name="country" class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Tous les pays</option>
                        @foreach(['Bénin', 'Burkina Faso', 'Cameroun', 'Côte d\'Ivoire', 'France', 'Gabon', 'Ghana', 'Guinée', 'Mali', 'Niger', 'Nigeria', 'Sénégal', 'Togo'] as $country)
                            <option value="{{ $country }}" {{ request('country') == $country ? 'selected' : '' }}>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-calendar-alt text-primary-500 mr-1"></i> Date de début
                    </label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-calendar-alt text-primary-500 mr-1"></i> Date de fin
                    </label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
                    <i class="fas fa-filter mr-2"></i> Filtrer
                </button>
                <a href="{{ route('admin.deliverers.index') }}" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-all">
                    <i class="fas fa-times mr-2"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm">Total Livreurs</p>
                    <p class="text-2xl font-bold">{{ \App\Models\User::where('role', 'livreur')->count() }}</p>
                </div>
                <i class="fas fa-truck text-3xl text-orange-200"></i>
            </div>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Entreprises</p>
                    <p class="text-2xl font-bold">{{ \App\Models\User::where('role', 'livreur')->whereNotNull('company_name')->distinct('company_name')->count('company_name') }}</p>
                </div>
                <i class="fas fa-building text-3xl text-blue-200"></i>
            </div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Ce mois</p>
                    <p class="text-2xl font-bold">{{ \App\Models\User::where('role', 'livreur')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count() }}</p>
                </div>
                <i class="fas fa-calendar-check text-3xl text-green-200"></i>
            </div>
        </div>
    </div>

    <!-- Deliverers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($deliverers as $deliverer)
            <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden hover:border-primary-500/50 transition-all">
                <!-- Company Header -->
                <div class="bg-dark-50 p-4 flex items-center gap-4 border-b border-dark-200">
                    @if($deliverer->company_logo)
                        <img src="{{ Storage::url($deliverer->company_logo) }}" alt="{{ $deliverer->company_name }}"
                             class="w-14 h-14 rounded-lg object-cover border border-dark-300">
                    @else
                        <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-xl font-bold">
                            {{ strtoupper(substr($deliverer->company_name ?? 'L', 0, 1)) }}
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <h3 class="text-white font-semibold truncate">{{ $deliverer->company_name ?? 'Sans entreprise' }}</h3>
                        <p class="text-gray-400 text-sm truncate">{{ $deliverer->country ?? 'Non renseigné' }}</p>
                    </div>
                </div>

                <!-- Deliverer Info -->
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                            {{ strtoupper(substr($deliverer->first_name, 0, 1)) }}{{ strtoupper(substr($deliverer->last_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-white font-medium truncate">{{ $deliverer->name }}</p>
                            <p class="text-gray-400 text-xs flex items-center gap-1">
                                @if($deliverer->gender == 'male')
                                    <i class="fas fa-mars text-blue-500"></i>
                                @elseif($deliverer->gender == 'female')
                                    <i class="fas fa-venus text-pink-500"></i>
                                @endif
                                {{ $deliverer->gender == 'male' ? 'Homme' : ($deliverer->gender == 'female' ? 'Femme' : 'Autre') }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex items-center text-gray-400">
                            <i class="fas fa-envelope text-primary-500 w-5 mr-2"></i>
                            <span class="truncate">{{ $deliverer->email }}</span>
                        </div>
                        @if($deliverer->phone)
                            <div class="flex items-center text-gray-400">
                                <i class="fas fa-phone text-primary-500 w-5 mr-2"></i>
                                <span>{{ $deliverer->phone }}</span>
                            </div>
                        @endif
                        <div class="flex items-center text-gray-400">
                            <i class="fas fa-calendar text-primary-500 w-5 mr-2"></i>
                            <span>Inscrit le {{ $deliverer->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="border-t border-dark-200 p-4 flex justify-end gap-2">
                    <a href="{{ route('admin.deliverers.show', $deliverer) }}"
                       class="px-3 py-2 bg-blue-500/20 text-blue-300 rounded-lg hover:bg-blue-500/30 transition-colors text-sm"
                       title="Voir">
                        <i class="fas fa-eye mr-1"></i> Voir
                    </a>
                    <a href="{{ route('admin.deliverers.edit', $deliverer) }}"
                       class="px-3 py-2 bg-primary-500/20 text-primary-300 rounded-lg hover:bg-primary-500/30 transition-colors text-sm"
                       title="Modifier">
                        <i class="fas fa-edit mr-1"></i> Modifier
                    </a>
                    <form action="{{ route('admin.deliverers.destroy', $deliverer) }}" method="POST" class="inline"
                          onsubmit="return confirm('Supprimer ce livreur partenaire ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-3 py-2 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/30 transition-colors text-sm">
                            <i class="fas fa-trash mr-1"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="bg-dark-100 rounded-xl p-12 text-center border border-dark-200">
                    <i class="fas fa-truck text-5xl text-gray-600 mb-4"></i>
                    <p class="text-lg text-gray-400">Aucun livreur partenaire trouvé</p>
                    <p class="text-sm text-gray-500 mt-1">Ajoutez votre premier livreur partenaire</p>
                    <a href="{{ route('admin.deliverers.create') }}"
                       class="mt-4 inline-block px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-plus mr-2"></i> Ajouter un livreur
                    </a>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($deliverers->hasPages())
        <div class="mt-6">
            {{ $deliverers->links() }}
        </div>
    @endif
</div>
@endsection
