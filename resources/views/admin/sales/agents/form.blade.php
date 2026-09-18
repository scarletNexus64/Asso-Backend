@extends('admin.layouts.app')

@section('title', $agent->exists ? 'Modifier le commercial' : 'Nouveau commercial')
@section('header', 'Commerciaux')

@section('content')
@php
    $input = 'w-full px-4 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500';
@endphp
<div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $agent->exists ? 'Modifier ' . $agent->full_name : 'Nouveau commercial' }}</h1>
            <p class="text-gray-400 mt-1">Le code est saisi par le vendeur lorsqu'il souscrit un forfait.</p>
        </div>
        <a href="{{ $agent->exists ? route('admin.sales.agents.show', $agent) : route('admin.sales.agents.index') }}"
           class="px-4 py-2 bg-dark-100 border border-dark-200 hover:bg-dark-50 text-gray-100 rounded-lg transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Retour
        </a>
    </div>

    <div class="bg-dark-100 border border-dark-200 rounded-lg p-6">
        <form method="POST" action="{{ $agent->exists ? route('admin.sales.agents.update', $agent) : route('admin.sales.agents.store') }}">
            @csrf
            @if($agent->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $agent->first_name) }}" required maxlength="100" class="{{ $input }}">
                    @error('first_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name', $agent->last_name) }}" required maxlength="100" class="{{ $input }}">
                    @error('last_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" maxlength="30" class="{{ $input }}">
                    @error('phone')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" value="{{ old('email', $agent->email) }}" class="{{ $input }}">
                    @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Code commercial</label>
                    <input type="text" name="code" value="{{ old('code', $agent->code) }}" maxlength="32" placeholder="Laisser vide pour générer automatiquement"
                           class="{{ $input }} font-mono uppercase">
                    <p class="text-xs text-gray-500 mt-1">Lettres, chiffres et tirets. Le code déjà utilisé reste dans l'historique s'il est changé.</p>
                    @error('code')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Taux particulier</label>
                    <div class="relative">
                        <input type="number" name="commission_rate" value="{{ old('commission_rate', $agent->commission_rate) }}" min="0" max="100" step="0.01"
                               placeholder="{{ (float) $defaultRate }} (taux par défaut)" class="{{ $input }} pr-8">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Vide = taux par défaut ({{ (float) $defaultRate }} %).</p>
                    @error('commission_rate')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Compte ASSO lié (email) <span class="text-gray-500 font-normal">— facultatif</span></label>
                    <input type="email" name="user_email" value="{{ old('user_email', $agent->user?->email) }}" class="{{ $input }}">
                    <p class="text-xs text-gray-500 mt-1">Si le commercial a un compte, il ne pourra pas utiliser son propre code pour ses souscriptions.</p>
                    @error('user_email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Notes (accord commercial, coordonnées de versement…)</label>
                    <textarea name="notes" rows="3" maxlength="2000" class="{{ $input }}">{{ old('notes', $agent->notes) }}</textarea>
                    @error('notes')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center text-gray-300">
                        <input type="checkbox" name="is_active" value="1" class="rounded mr-2" @checked(old('is_active', $agent->is_active ?? true))>
                        Actif (le code est accepté à la souscription)
                    </label>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="px-6 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                    <i class="fas fa-save mr-2"></i>{{ $agent->exists ? 'Enregistrer' : 'Créer le commercial' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
