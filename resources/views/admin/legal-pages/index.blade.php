@extends('admin.layouts.app')

@section('title', 'Pages Légales')
@section('header', 'Pages Légales & Chartes')

@push('styles')
@include('admin.legal-pages._preview_styles')
@endpush

@php
    $contexts = \App\Models\LegalPage::CONTEXTS;
    $bySlug = $legalPages->keyBy('slug');
    $selected = $bySlug->has(request('page')) ? request('page') : $legalPages->first()?->slug;
    $formatSize = fn (int $bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', ' ') . ' Mo' : ceil($bytes / 1024) . ' Ko';
@endphp

@section('content')
<div class="space-y-6" x-data="{ current: @js($selected) }"
     x-init="$watch('current', v => { const u = new URL(location); u.searchParams.set('page', v); history.replaceState(null, '', u); })">

    <!-- En-tête -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-gray-400 text-sm">
            Documents juridiques affichés dans l'application. Importez un document Word (.docx) pour remplacer un contenu,
            ou modifiez le texte directement.
        </p>
        <a href="{{ route('admin.legal-pages.create') }}"
           class="shrink-0 px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-md text-sm">
            <i class="fas fa-plus mr-2"></i> Nouvelle page
        </a>
    </div>

    <!-- Acceptations obligatoires dans l'application -->
    <div class="bg-dark-100 rounded-lg border border-dark-200 p-4">
        <div class="flex items-center gap-2 mb-3">
            <i class="fas fa-file-signature text-primary-500"></i>
            <h3 class="text-sm font-semibold text-white">Acceptation obligatoire dans l'application</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach($contexts as $key => $context)
                <div class="rounded-lg bg-dark-50 border border-dark-200 p-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold text-gray-300">{{ $context['label'] }}</span>
                        <code class="text-[10px] text-gray-500">{{ $key }}</code>
                    </div>
                    <ul class="space-y-1">
                        @foreach($context['slugs'] as $slug)
                            @php $p = $bySlug->get($slug); @endphp
                            <li class="flex items-center gap-2 text-xs">
                                @if($p && $p->is_active)
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <button type="button" @click="current = '{{ $slug }}'" class="text-gray-200 hover:text-primary-400 text-left truncate">{{ $p->title }}</button>
                                @elseif($p)
                                    <i class="fas fa-exclamation-triangle text-amber-400" title="Page désactivée : l'application ne la reçoit pas"></i>
                                    <button type="button" @click="current = '{{ $slug }}'" class="text-amber-300 hover:text-amber-200 text-left truncate">{{ $p->title }} (désactivée)</button>
                                @else
                                    <i class="fas fa-times-circle text-red-500"></i>
                                    <span class="text-red-400 truncate">{{ $slug }} — page manquante</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    @if($legalPages->isEmpty())
        <div class="bg-dark-100 rounded-lg border border-dark-200 p-12 text-center">
            <i class="fas fa-file-contract text-6xl text-gray-600 mb-4"></i>
            <h3 class="text-xl font-semibold text-white mb-2">Aucune page légale</h3>
            <p class="text-gray-400 mb-6">Créez une page ou importez un document Word.</p>
            <a href="{{ route('admin.legal-pages.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg shadow-md">
                <i class="fas fa-plus mr-2"></i> Créer une page
            </a>
        </div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">

        <!-- Liste des documents -->
        <nav class="bg-dark-100 rounded-lg border border-dark-200 overflow-hidden lg:sticky lg:top-4">
            <div class="px-4 py-3 border-b border-dark-200 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Documents ({{ $legalPages->count() }})
            </div>
            <ul class="divide-y divide-dark-200 max-h-[70vh] overflow-y-auto">
                @foreach($legalPages as $page)
                    @php $required = \App\Models\LegalPage::contextsFor($page->slug); @endphp
                    <li>
                        <button type="button" @click="current = '{{ $page->slug }}'"
                                :class="current === '{{ $page->slug }}' ? 'bg-primary-500/10 border-l-primary-500' : 'border-l-transparent hover:bg-dark-50'"
                                class="w-full text-left px-4 py-3 border-l-4 transition-colors">
                            <div class="flex items-start gap-3">
                                <i class="fas {{ !empty($documents[$page->id]) ? 'fa-file-word text-blue-400' : 'fa-file-alt text-gray-500' }} mt-0.5"></i>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-white truncate">{{ $page->title }}</span>
                                        <span class="w-2 h-2 rounded-full shrink-0 {{ $page->is_active ? 'bg-green-500' : 'bg-gray-500' }}"
                                              title="{{ $page->is_active ? 'Active' : 'Désactivée' }}"></span>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ $page->slug }}</div>
                                    @if($required)
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach($required as $key)
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                    Requis : {{ $contexts[$key]['label'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <!-- Document sélectionné -->
        <div class="min-w-0">
            @foreach($legalPages as $page)
                @php
                    $docs = $documents[$page->id] ?? [];
                    $doc = $docs[0] ?? null;
                    $required = \App\Models\LegalPage::contextsFor($page->slug);
                    $apiUrl = url('/api/v1/legal-pages/' . $page->slug);
                @endphp
                <article x-show="current === '{{ $page->slug }}'" @if($page->slug !== $selected) x-cloak @endif
                         class="bg-dark-100 rounded-lg border border-dark-200" x-data="{ replacing: false, history: false }">

                    <!-- En-tête du document -->
                    <header class="p-5 border-b border-dark-200 space-y-4">
                        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-white">{{ $page->title }}</h2>
                                    @if($page->is_active)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-500/10 text-green-500 border border-green-500/20">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-500/10 text-gray-400 border border-gray-500/20">Désactivée</span>
                                    @endif
                                    @foreach($required as $key)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            Requis : {{ $contexts[$key]['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Mise à jour le {{ $page->updated_at?->displayTz()->format('d/m/Y à H:i') }}
                                    · {{ number_format(str_word_count(strip_tags($page->content ?? '')), 0, ',', ' ') }} mots
                                    · ordre {{ $page->order }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <button type="button" @click="replacing = !replacing"
                                        class="px-3 py-2 text-sm rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white hover:from-primary-600 hover:to-primary-700 shadow-md">
                                    <i class="fas fa-file-upload mr-1"></i> Remplacer par un .docx
                                </button>
                                <a href="{{ route('admin.legal-pages.edit', $page) }}"
                                   class="px-3 py-2 text-sm rounded-lg bg-dark-300 text-white hover:bg-dark-400">
                                    <i class="fas fa-edit mr-1"></i> Modifier le texte
                                </a>
                                <a href="{{ route('legal.show', $page->slug) }}" target="_blank" rel="noopener"
                                   class="p-2 text-gray-400 hover:text-primary-400" title="Voir la page publique">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <form action="{{ route('admin.legal-pages.toggle', $page) }}" method="POST" class="inline"
                                      @if($page->is_active && $required) data-confirm="Cette page est obligatoire dans l'application. La désactiver la retire de l'API. Continuer ?" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-2 text-gray-400 hover:text-primary-400"
                                            title="{{ $page->is_active ? 'Désactiver' : 'Activer' }}">
                                        <i class="fas fa-{{ $page->is_active ? 'eye-slash' : 'eye' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.legal-pages.destroy', $page) }}" method="POST" class="inline"
                                      data-confirm="Supprimer définitivement « {{ $page->title }} » ?{{ $required ? ' Cette page est obligatoire dans l\'application.' : '' }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-gray-400 hover:text-red-500" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Remplacement par un document Word -->
                        <form x-show="replacing" x-cloak action="{{ route('admin.legal-pages.document', $page) }}" method="POST"
                              enctype="multipart/form-data" class="rounded-lg border border-primary-500/30 bg-primary-500/5 p-4 space-y-3"
                              data-confirm="Le contenu actuel sera remplacé par celui du document (l'ancien texte est sauvegardé). Continuer ?">
                            @csrf
                            <p class="text-sm text-gray-300">
                                Le texte du document Word remplacera le contenu actuel de cette page. L'ancien texte et les anciens fichiers sont conservés.
                            </p>
                            <input type="file" name="document" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="replacing = false" class="px-3 py-2 text-sm rounded-lg bg-dark-300 text-white hover:bg-dark-400">Annuler</button>
                                <button type="submit" class="px-3 py-2 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-700">
                                    <i class="fas fa-check mr-1"></i> Importer
                                </button>
                            </div>
                        </form>

                        <!-- Document source + API -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-dark-50 border border-dark-200 p-3">
                                <div class="text-gray-500 mb-1">Document Word source</div>
                                @if($doc)
                                    <div class="flex items-center justify-between gap-2">
                                        <a href="{{ $doc['url'] }}" class="text-blue-400 hover:text-blue-300 truncate" download>
                                            <i class="fas fa-file-word mr-1"></i>{{ $doc['name'] }}
                                        </a>
                                        <span class="text-gray-500 shrink-0">{{ $formatSize($doc['size']) }}</span>
                                    </div>
                                    @if($doc['uploaded_at'])
                                        <div class="text-gray-500 mt-1">Importé le {{ $doc['uploaded_at']->displayTz()->format('d/m/Y à H:i') }}</div>
                                    @endif
                                    @if(count($docs) > 1)
                                        <button type="button" @click="history = !history" class="mt-1 text-gray-400 hover:text-gray-200">
                                            <i class="fas fa-history mr-1"></i>{{ count($docs) - 1 }} version(s) précédente(s)
                                        </button>
                                        <ul x-show="history" x-cloak class="mt-1 space-y-1">
                                            @foreach(array_slice($docs, 1) as $old)
                                                <li class="flex justify-between gap-2">
                                                    <a href="{{ $old['url'] }}" download class="text-gray-400 hover:text-gray-200 truncate">{{ $old['name'] }}</a>
                                                    <span class="text-gray-600 shrink-0">{{ $old['uploaded_at']?->displayTz()->format('d/m/Y H:i') }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @else
                                    <span class="text-gray-400">Aucun — contenu rédigé dans l'éditeur</span>
                                @endif
                            </div>
                            <div class="rounded-lg bg-dark-50 border border-dark-200 p-3" x-data="{ copied: false }">
                                <div class="text-gray-500 mb-1">Endpoint de l'application</div>
                                <div class="flex items-center gap-2">
                                    <code class="text-gray-300 truncate">GET {{ $apiUrl }}</code>
                                    <button type="button" class="text-gray-400 hover:text-primary-400 shrink-0" title="Copier"
                                            @click="navigator.clipboard.writeText(@js($apiUrl)); copied = true; setTimeout(() => copied = false, 1500)">
                                        <i class="fas" :class="copied ? 'fa-check text-green-500' : 'fa-copy'"></i>
                                    </button>
                                </div>
                                @if($required)
                                    <div class="text-gray-500 mt-1 truncate">
                                        ou <code class="text-gray-400">?context={{ implode(',', $required) }}</code> sur /api/v1/legal-pages
                                    </div>
                                @endif
                            </div>
                        </div>
                    </header>

                    <!-- Contenu -->
                    <div class="p-5 sm:p-8">
                        @if(filled(strip_tags($page->content ?? '')))
                            <div class="legal-preview">{!! $page->content !!}</div>
                        @else
                            <p class="text-gray-500 text-sm italic">Cette page n'a pas encore de contenu.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
