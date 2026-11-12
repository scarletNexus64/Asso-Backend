@extends('admin.layouts.app')
@section('content')
<div class="p-6 text-white max-w-4xl">
<h1 class="text-2xl font-bold mb-6">{{ $reference->exists ? 'Modifier la référence' : 'Nouvelle référence ASSO' }}</h1>
@if($errors->any())<div class="text-red-400 mb-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ $reference->exists ? route('admin.product-references.update', $reference) : route('admin.product-references.store') }}">
@csrf @if($reference->exists) @method('PUT') @endif
<div class="space-y-4 bg-dark-100 rounded p-6 mb-4">
@foreach(['name' => 'Nom de la référence', 'city' => 'Ville', 'country' => 'Pays', 'quarter' => 'Quartier'] as $field => $label)
<label class="block">{{ $label }} <input class="block w-full bg-dark-50 rounded p-2 mt-1" name="{{ $field }}" value="{{ old($field, $reference->$field) }}" @if($field === 'name') required @endif></label>
@endforeach
<label class="block">Statut <select name="is_active" class="block w-full bg-dark-50 rounded p-2 mt-1"><option value="1" @selected(old('is_active', $reference->exists ? (int) $reference->is_active : 1) == 1)>Active</option><option value="0" @selected(old('is_active', $reference->is_active) == 0 && $reference->exists)>Désactivée</option></select></label>
</div>
@include('admin.partials.google-map', ['id' => 'reference-map', 'label' => 'Localisation de la référence', 'required' => false, 'latitude' => old('latitude', $reference->latitude), 'longitude' => old('longitude', $reference->longitude), 'address' => old('address', $reference->address)])
<button class="bg-primary-500 rounded px-4 py-2" type="submit">Enregistrer</button> <a href="{{ route('admin.product-references.index') }}">Annuler</a>
</form></div>
@endsection
