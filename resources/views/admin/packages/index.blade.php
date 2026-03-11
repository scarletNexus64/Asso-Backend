@extends('admin.layouts.app')

@section('content')
<div class="p-6 space-y-6">
    <!-- En-tête -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-100">Gestion des Packages</h1>
            <p class="text-gray-400 mt-1">Gérez les forfaits d'abonnement de la plateforme</p>
        </div>
        <a href="{{ route('admin.packages.create') }}"
            class="px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
            <i class="fas fa-plus mr-2"></i>
            Nouveau Package
        </a>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Packages</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-blue-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-box text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Actifs</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['active'] }}</p>
                </div>
                <div class="bg-green-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">Populaires</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['popular'] }}</p>
                </div>
                <div class="bg-yellow-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-star text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Stockage</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['storage'] }}</p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-hdd text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Boost</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['boost'] }}</p>
                </div>
                <div class="bg-purple-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-rocket text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Certification</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['certification'] }}</p>
                </div>
                <div class="bg-green-400 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-certificate text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-dark-100 border border-dark-200 rounded-lg p-4">
        <form method="GET" action="{{ route('admin.packages.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="type" class="block text-sm font-medium text-gray-300 mb-2">Type de package</label>
                <select id="type" name="type"
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
                    <option value="">Tous</option>
                    <option value="storage" {{ request('type') === 'storage' ? 'selected' : '' }}>Stockage</option>
                    <option value="boost" {{ request('type') === 'boost' ? 'selected' : '' }}>Boost</option>
                    <option value="certification" {{ request('type') === 'certification' ? 'selected' : '' }}>Certification</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Statut</label>
                <select id="status" name="status"
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
                    <option value="">Tous</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Actifs</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactifs</option>
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-filter mr-2"></i>
                Filtrer
            </button>
            @if(request()->hasAny(['type', 'status']))
                <a href="{{ route('admin.packages.index') }}"
                    class="px-6 py-2 bg-dark-50 border border-dark-200 hover:bg-dark-100 text-gray-100 rounded-lg transition-colors">
                    Réinitialiser
                </a>
            @endif
        </form>
    </div>

    <!-- Liste des packages -->
    @if($packagesByType)
        <!-- Affichage groupé par catégorie -->

        <!-- Packages Espace de Stockage -->
        @if($packagesByType['storage']->count() > 0)
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 p-3 rounded-lg">
                    <i class="fas fa-hdd text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">Espace de Stockage</h2>
                    <p class="text-gray-400 text-sm">Packages pour augmenter l'espace de stockage</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($packagesByType['storage'] as $package)
                    @include('admin.packages.partials.package-card', ['package' => $package])
                @endforeach
            </div>
        </div>
        @endif

        <!-- Packages Boost -->
        @if($packagesByType['boost']->count() > 0)
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="bg-purple-600 p-3 rounded-lg">
                    <i class="fas fa-rocket text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">Boost de Visibilité</h2>
                    <p class="text-gray-400 text-sm">Packages pour booster vos produits et boutiques</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($packagesByType['boost'] as $package)
                    @include('admin.packages.partials.package-card', ['package' => $package])
                @endforeach
            </div>
        </div>
        @endif

        <!-- Packages Certification -->
        @if($packagesByType['certification']->count() > 0)
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="bg-green-600 p-3 rounded-lg">
                    <i class="fas fa-certificate text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">Certification</h2>
                    <p class="text-gray-400 text-sm">Packages de certification pour les boutiques</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($packagesByType['certification'] as $package)
                    @include('admin.packages.partials.package-card', ['package' => $package])
                @endforeach
            </div>
        </div>
        @endif

        @if($packagesByType['storage']->count() == 0 && $packagesByType['boost']->count() == 0 && $packagesByType['certification']->count() == 0)
            <div class="text-center py-12 bg-dark-100 border border-dark-200 rounded-lg">
                <i class="fas fa-box-open text-6xl text-gray-600 mb-4"></i>
                <p class="text-gray-400 text-lg">Aucun package trouvé</p>
            </div>
        @endif
    @else
        <!-- Affichage normal avec pagination (quand un filtre de type est appliqué) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($packages as $package)
                @include('admin.packages.partials.package-card', ['package' => $package])
            @empty
                <div class="col-span-3 text-center py-12 bg-dark-100 border border-dark-200 rounded-lg">
                    <i class="fas fa-box-open text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">Aucun package trouvé</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($packages && $packages->hasPages())
            <div class="flex justify-center">
                {{ $packages->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
