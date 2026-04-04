@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Préférences des Utilisateurs</h1>
            <p class="text-gray-400 mt-1">Vue d'ensemble des catégories sélectionnées depuis l'application mobile</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">Utilisateurs avec préférences</p>
                    <p class="text-3xl font-bold">{{ count($usersWithPreferences) }}</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users-cog text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-pink-500 to-pink-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-pink-100 text-sm mb-1">Sans préférences</p>
                    <p class="text-3xl font-bold">
                        {{ \App\Models\User::where(function($q) {
                            $q->whereNull('preferences')
                              ->orWhere('preferences', 'LIKE', '[]')
                              ->orWhere('preferences', 'LIKE', 'null')
                              ->orWhere('preferences', '=', '');
                        })->count() }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-slash text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-100 text-sm mb-1">Préférences uniques</p>
                    <p class="text-3xl font-bold">{{ count($preferenceStats) }}</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tags text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    @if(empty($preferenceStats))
        <!-- Empty State -->
        <div class="bg-dark-100 rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-heart-broken text-6xl text-gray-600 mb-4"></i>
            <h2 class="text-xl font-bold text-gray-300 mb-2">Aucune préférence enregistrée</h2>
            <p class="text-gray-500">Les utilisateurs n'ont pas encore défini leurs catégories d'intérêt depuis l'application mobile.</p>
        </div>
    @else
        <!-- Preferences Grid -->
        <div class="bg-dark-100 rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-primary-500"></i>
                Catégories par popularité
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($preferenceStats as $prefSlug => $data)
                    @php
                        // Parse preference name from slug
                        $parts = explode('_', $prefSlug);
                        $categoryName = ucfirst($parts[0] ?? '');
                        $subName = ucfirst($parts[1] ?? '');

                        // Calculate percentage
                        $percentage = count($usersWithPreferences) > 0
                            ? round(($data['count'] / count($usersWithPreferences)) * 100, 1)
                            : 0;
                    @endphp

                    <div class="bg-dark-50 rounded-lg p-5 border border-dark-300 hover:border-primary-500 transition-all hover:shadow-lg">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-start gap-3">
                                <div class="w-12 h-12 flex items-center justify-center bg-primary-500/20 rounded-lg flex-shrink-0">
                                    <i class="fas fa-tag text-primary-500 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-white font-bold text-lg">{{ $categoryName }}</h3>
                                    <p class="text-gray-400 text-sm">{{ $subName }}</p>
                                    <code class="text-[10px] text-gray-500 bg-dark-100 px-2 py-0.5 rounded mt-1 inline-block">{{ $prefSlug }}</code>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-primary-400">{{ $data['count'] }}</div>
                                <div class="text-xs text-gray-400">utilisateur(s)</div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                                <span>Popularité</span>
                                <span class="font-semibold text-primary-400">{{ $percentage }}%</span>
                            </div>
                            <div class="w-full bg-dark-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-2 rounded-full transition-all duration-500"
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- Users List -->
                        @if(!empty($data['users']))
                            <div class="pt-4 border-t border-dark-300">
                                <p class="text-xs text-gray-500 mb-2">Utilisateurs intéressés:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(array_slice($data['users'], 0, 10) as $user)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-dark-100 rounded-lg text-xs text-gray-300 border border-dark-400 hover:border-primary-500 transition-all">
                                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                                                {{ strtoupper(substr($user->first_name, 0, 1)) }}
                                            </div>
                                            <span class="font-medium">{{ $user->first_name }} {{ substr($user->last_name, 0, 1) }}.</span>
                                        </div>
                                    @endforeach
                                    @if(count($data['users']) > 10)
                                        <span class="text-xs text-gray-500 py-1.5 px-2">+{{ count($data['users']) - 10 }} autre(s)</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Users Detail Table -->
        <div class="bg-dark-100 rounded-xl shadow-lg mt-6 overflow-hidden">
            <div class="p-6 border-b border-dark-200">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list text-primary-500"></i>
                    Liste des utilisateurs avec préférences
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-200">
                    <thead class="bg-dark-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                Utilisateur
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                Contact
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                Nombre de préférences
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                Catégories
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-dark-100 divide-y divide-dark-200">
                        @foreach($usersWithPreferences as $user)
                            <tr class="hover:bg-dark-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-bold">
                                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-white">
                                                {{ $user->name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ID: {{ $user->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-400">
                                        <div><i class="fas fa-envelope text-primary-500 mr-1"></i> {{ $user->email }}</div>
                                        @if($user->phone)
                                            <div class="mt-1"><i class="fas fa-phone text-primary-500 mr-1"></i> {{ $user->phone }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-primary-500/20 text-primary-300 border border-primary-500/50">
                                        {{ is_array($user->preferences) && isset($user->preferences['categories']) ? count($user->preferences['categories']) : 0 }} catégories
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if(is_array($user->preferences) && isset($user->preferences['categories']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($user->preferences['categories'], 0, 3) as $pref)
                                                <span class="text-xs px-2 py-1 bg-dark-50 text-gray-400 rounded border border-dark-300">
                                                    {{ $pref }}
                                                </span>
                                            @endforeach
                                            @if(count($user->preferences['categories']) > 3)
                                                <span class="text-xs px-2 py-1 text-gray-500">
                                                    +{{ count($user->preferences['categories']) - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
