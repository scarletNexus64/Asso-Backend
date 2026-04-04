@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Tokens FCM (Notifications Push)</h1>
        <p class="text-gray-400">Gérez les tokens de notifications push des utilisateurs</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Total Utilisateurs</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($stats['total_users']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Avec Tokens</p>
                    <p class="text-2xl font-bold text-green-400">{{ number_format($stats['users_with_tokens']) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-check text-green-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Sans Tokens</p>
                    <p class="text-2xl font-bold text-red-400">{{ number_format($stats['users_without_tokens']) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-times text-red-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Total Tokens</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($stats['total_tokens']) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Tokens Actifs</p>
                    <p class="text-2xl font-bold text-green-400">{{ number_format($stats['active_tokens']) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl p-4 border border-dark-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Tokens Inactifs</p>
                    <p class="text-2xl font-bold text-gray-400">{{ number_format($stats['inactive_tokens']) }}</p>
                </div>
                <div class="w-12 h-12 bg-gray-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times-circle text-gray-500 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.fcm-tokens.index') }}" class="flex flex-wrap gap-4">
            <!-- Search -->
            <div class="flex-1 min-w-[250px]">
                <label for="search" class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-search text-primary-400 mr-1"></i>
                    Rechercher
                </label>
                <input type="text" name="search" id="search" value="{{ $search }}"
                       class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                       placeholder="Nom, email, téléphone...">
            </div>

            <!-- Filter Status -->
            <div class="w-full sm:w-auto">
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-filter text-primary-400 mr-1"></i>
                    Statut
                </label>
                <select name="status" id="status"
                        class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">Tous</option>
                    <option value="with_tokens" {{ $filterStatus === 'with_tokens' ? 'selected' : '' }}>Avec tokens</option>
                    <option value="without_tokens" {{ $filterStatus === 'without_tokens' ? 'selected' : '' }}>Sans tokens</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all">
                    <i class="fas fa-search mr-2"></i> Filtrer
                </button>
                <a href="{{ route('admin.fcm-tokens.index') }}"
                   class="px-6 py-2 bg-dark-300 text-white rounded-lg hover:bg-dark-400 transition-all">
                    <i class="fas fa-redo mr-2"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-dark-50 border-b border-dark-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Utilisateur</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Tokens Actifs</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Tokens</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Dernière activité</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-dark-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold mr-3">
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-white">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-400">ID: {{ $user->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">{{ $user->email }}</div>
                                <div class="text-xs text-gray-400">{{ $user->phone ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 text-sm font-semibold rounded-full
                                    {{ $user->active_tokens_count > 0 ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400' }}">
                                    {{ $user->active_tokens_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 text-sm font-semibold rounded-full bg-blue-500/20 text-blue-400">
                                    {{ $user->device_tokens_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($user->device_tokens_count > 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                                        <i class="fas fa-bell mr-1"></i> Peut recevoir
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                                        <i class="fas fa-bell-slash mr-1"></i> Pas de token
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-400">
                                @if($user->deviceTokens->first())
                                    {{ $user->deviceTokens->first()->last_used_at ? $user->deviceTokens->first()->last_used_at->diffForHumans() : 'Jamais' }}
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('admin.fcm-tokens.show', $user) }}"
                                   class="inline-flex items-center px-3 py-1 bg-primary-500/20 text-primary-400 rounded-lg hover:bg-primary-500/30 transition-all text-sm">
                                    <i class="fas fa-eye mr-1"></i> Voir détails
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-inbox text-gray-500 text-5xl mb-4"></i>
                                    <p class="text-gray-400 text-lg">Aucun utilisateur trouvé</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-dark-200">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
