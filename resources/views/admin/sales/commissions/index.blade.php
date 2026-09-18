@extends('admin.layouts.app')

@section('title', 'Commissions des commerciaux')
@section('header', 'Commerciaux')

@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' F'; @endphp
<div class="p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Commissions des commerciaux</h1>
            <p class="text-gray-400 mt-1">Une commission est créée quand un forfait souscrit avec un code commercial est payé.</p>
        </div>
        <a href="{{ route('admin.sales.commissions.export', request()->query()) }}" class="px-4 py-2 bg-dark-200 text-white rounded-lg hover:bg-dark-300">
            <i class="fas fa-file-csv mr-2"></i>Export CSV
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <a href="{{ route('admin.sales.commissions.index', ['status' => 'due']) }}" class="bg-dark-100 rounded-xl shadow-lg p-6 hover:ring-1 hover:ring-yellow-500/50">
            <p class="text-gray-400 text-sm">À payer ({{ $stats['due_count'] }})</p>
            <p class="text-2xl font-bold text-yellow-500 mt-1">{{ $fmt($stats['due_amount']) }}</p>
        </a>
        <a href="{{ route('admin.sales.commissions.index', ['status' => 'paid']) }}" class="bg-dark-100 rounded-xl shadow-lg p-6 hover:ring-1 hover:ring-green-500/50">
            <p class="text-gray-400 text-sm">Payées ({{ $stats['paid_count'] }})</p>
            <p class="text-2xl font-bold text-green-500 mt-1">{{ $fmt($stats['paid_amount']) }}</p>
        </a>
    </div>

    <div class="bg-dark-100 rounded-xl shadow-lg p-4 mb-6">
        <form method="GET" action="{{ route('admin.sales.commissions.index') }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Vendeur, code, référence…"
                   class="lg:col-span-2 px-4 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg focus:outline-none focus:border-primary-500">
            <select name="status" class="px-4 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg">
                <option value="">Tous les statuts</option>
                @foreach(\App\Models\SalesCommission::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="agent_id" class="px-4 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg">
                <option value="">Tous les commerciaux</option>
                @foreach($agents as $a)
                    <option value="{{ $a->id }}" @selected((string) request('agent_id') === (string) $a->id)>{{ $a->last_name }} {{ $a->first_name }} ({{ $a->code }})</option>
                @endforeach
            </select>
            <select name="package_id" class="px-4 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg">
                <option value="">Tous les forfaits</option>
                @foreach($packages as $p)
                    <option value="{{ $p->id }}" @selected((string) request('package_id') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input type="date" name="start_date" value="{{ request('start_date') }}" title="Du" class="w-full px-2 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg">
                <input type="date" name="end_date" value="{{ request('end_date') }}" title="Au" class="w-full px-2 py-2 bg-dark-50 text-white border border-dark-200 rounded-lg">
            </div>
            <button type="submit" class="px-6 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600"><i class="fas fa-search mr-2"></i>Filtrer</button>
            <a href="{{ route('admin.sales.commissions.index') }}" class="px-6 py-2 bg-dark-200 text-white rounded-lg hover:bg-dark-300 text-center"><i class="fas fa-redo mr-2"></i>Réinitialiser</a>
        </form>
    </div>

    <p class="text-sm text-gray-400 mb-3">
        {{ $totals['count'] }} vente(s) · {{ $fmt($totals['amount_paid']) }} payés par les vendeurs · {{ $fmt($totals['commission']) }} de commissions
    </p>

    @include('admin.sales.partials.commissions-table', ['commissions' => $commissions, 'showAgent' => true])
</div>
@endsection
