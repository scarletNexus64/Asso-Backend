@extends('admin.layouts.app')

@section('content')
<div class="p-6 space-y-6">
    <!-- En-tête -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-100">Support Client</h1>
            <p class="text-gray-400 mt-1">Gérez les tickets et répondez aux demandes des utilisateurs</p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Tickets</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-blue-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Ouverts</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['open'] }}</p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-envelope-open text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">En cours</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="bg-yellow-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-spinner text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Résolus</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['resolved'] }}</p>
                </div>
                <div class="bg-green-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-600 to-gray-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-100 text-sm font-medium">Fermés</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['closed'] }}</p>
                </div>
                <div class="bg-gray-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-lock text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-600 to-red-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm font-medium">Urgents</p>
                    <p class="text-3xl font-bold mt-2">{{ $stats['urgent'] }}</p>
                </div>
                <div class="bg-red-500 bg-opacity-30 p-3 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et Recherche -->
    <div class="bg-dark-100 border border-dark-200 rounded-lg p-4">
        <form method="GET" action="{{ route('admin.support.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-sm font-medium text-gray-300 mb-2">Recherche</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                    placeholder="Numéro de ticket ou sujet..."
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex-1 min-w-[150px]">
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Statut</label>
                <select id="status" name="status"
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
                    <option value="">Tous</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Ouverts</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>En cours</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Résolus</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Fermés</option>
                </select>
            </div>

            <div class="flex-1 min-w-[150px]">
                <label for="priority" class="block text-sm font-medium text-gray-300 mb-2">Priorité</label>
                <select id="priority" name="priority"
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
                    <option value="">Toutes</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Basse</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Moyenne</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>Haute</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                </select>
            </div>

            <div class="flex-1 min-w-[150px]">
                <label for="category" class="block text-sm font-medium text-gray-300 mb-2">Catégorie</label>
                <select id="category" name="category"
                    class="w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500">
                    <option value="">Toutes</option>
                    <option value="technique" {{ request('category') === 'technique' ? 'selected' : '' }}>Technique</option>
                    <option value="payment" {{ request('category') === 'payment' ? 'selected' : '' }}>Paiement</option>
                    <option value="product" {{ request('category') === 'product' ? 'selected' : '' }}>Produit</option>
                    <option value="account" {{ request('category') === 'account' ? 'selected' : '' }}>Compte</option>
                    <option value="other" {{ request('category') === 'other' ? 'selected' : '' }}>Autre</option>
                </select>
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-search mr-2"></i>
                Rechercher
            </button>

            @if(request()->hasAny(['search', 'status', 'priority', 'category']))
                <a href="{{ route('admin.support.index') }}"
                    class="px-6 py-2 bg-dark-50 border border-dark-200 hover:bg-dark-100 text-gray-100 rounded-lg transition-colors">
                    Réinitialiser
                </a>
            @endif
        </form>
    </div>

    <!-- Liste des tickets (style boîte mail) -->
    <div class="bg-dark-100 border border-dark-200 rounded-lg overflow-hidden">
        @forelse($tickets as $ticket)
            <a href="{{ route('admin.support.show', $ticket) }}"
                class="block border-b border-dark-200 hover:bg-dark-50 transition-colors {{ $ticket->admin_id ? '' : 'bg-blue-900 bg-opacity-10' }}">
                <div class="p-4">
                    <div class="flex items-start gap-4">
                        <!-- Avatar utilisateur -->
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold">
                                {{ strtoupper(substr($ticket->user->name, 0, 1)) }}
                            </div>
                        </div>

                        <!-- Contenu principal -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-4 mb-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold text-gray-100">{{ $ticket->user->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $ticket->ticket_number }}</span>
                                        @if(!$ticket->admin_id)
                                            <span class="bg-blue-500 text-white px-2 py-0.5 rounded text-xs font-bold">
                                                NOUVEAU
                                            </span>
                                        @endif
                                    </div>
                                    <h3 class="text-gray-200 font-medium truncate">{{ $ticket->subject }}</h3>
                                    <p class="text-gray-400 text-sm mt-1 line-clamp-2">{{ $ticket->message }}</p>
                                </div>

                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm text-gray-400">{{ $ticket->created_at->diffForHumans() }}</p>
                                    @if($ticket->admin)
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-user-shield mr-1"></i>
                                            {{ $ticket->admin->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <!-- Badges -->
                            <div class="flex items-center gap-2 flex-wrap mt-3">
                                <span class="bg-{{ $ticket->status_color }}-600 text-white px-2 py-1 rounded text-xs font-bold">
                                    {{ $ticket->status_label }}
                                </span>
                                <span class="bg-{{ $ticket->priority_color }}-600 text-white px-2 py-1 rounded text-xs font-bold">
                                    <i class="fas fa-flag mr-1"></i>
                                    {{ $ticket->priority_label }}
                                </span>
                                <span class="bg-gray-600 text-white px-2 py-1 rounded text-xs">
                                    <i class="fas {{ $ticket->category_icon }} mr-1"></i>
                                    {{ $ticket->category_label }}
                                </span>
                                @if($ticket->replies_count > 0)
                                    <span class="text-gray-400 text-xs">
                                        <i class="fas fa-comments mr-1"></i>
                                        {{ $ticket->replies_count }} réponse(s)
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center py-12">
                <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                <p class="text-gray-400 text-lg">Aucun ticket trouvé</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($tickets->hasPages())
        <div class="flex justify-center">
            {{ $tickets->links() }}
        </div>
    @endif
</div>
@endsection
