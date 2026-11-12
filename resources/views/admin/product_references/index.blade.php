@extends('admin.layouts.app')
@section('content')
<div class="p-6 text-white">
    <div class="flex justify-between items-center mb-6"><h1 class="text-2xl font-bold">Références ASSO</h1><a class="bg-primary-500 px-4 py-2 rounded" href="{{ route('admin.product-references.create') }}">Nouvelle référence</a></div>
    @if(session('success'))<p class="text-green-400 mb-4">{{ session('success') }}</p>@endif
    <div class="bg-dark-100 rounded p-4">
        @forelse($references as $reference)
            <div class="border-b border-dark-300 py-3 flex justify-between gap-4"><div><strong>{{ $reference->name }}</strong> — {{ $reference->city }}<div class="text-gray-400">{{ $reference->address ?: 'Non géolocalisée : la boutique sert de départ' }} · {{ $reference->is_active ? 'Active' : 'Désactivée' }}</div></div><a class="text-primary-500" href="{{ route('admin.product-references.edit', $reference) }}">Modifier</a></div>
        @empty <p>Aucune référence enregistrée.</p> @endforelse
    </div>
    {{ $references->links() }}
</div>
@endsection
