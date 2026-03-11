@extends('admin.layouts.app')

@section('title', 'Créer une Page Légale')
@section('header', 'Créer une Nouvelle Page Légale')

@push('styles')
<!-- Quill Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .ql-toolbar {
        background-color: #374151 !important;
        border: 1px solid #4b5563 !important;
        border-radius: 0.5rem 0.5rem 0 0 !important;
    }
    .ql-container {
        background-color: #1f2937 !important;
        border: 1px solid #4b5563 !important;
        border-top: none !important;
        border-radius: 0 0 0.5rem 0.5rem !important;
        color: #e5e7eb !important;
        font-size: 14px !important;
        min-height: 400px !important;
    }
    .ql-editor {
        color: #e5e7eb !important;
        min-height: 400px !important;
    }
    .ql-editor.ql-blank::before {
        color: #9ca3af !important;
    }
    .ql-snow .ql-stroke {
        stroke: #e5e7eb !important;
    }
    .ql-snow .ql-fill {
        fill: #e5e7eb !important;
    }
    .ql-snow .ql-picker-label {
        color: #e5e7eb !important;
    }
    .ql-snow .ql-picker-options {
        background-color: #374151 !important;
        border: 1px solid #4b5563 !important;
    }
    .ql-snow .ql-picker-item {
        color: #e5e7eb !important;
    }
    .ql-snow .ql-picker-item:hover {
        background-color: #4b5563 !important;
    }
    .ql-toolbar button:hover .ql-stroke,
    .ql-toolbar button:hover .ql-fill {
        stroke: #60a5fa !important;
        fill: #60a5fa !important;
    }
    .ql-toolbar button.ql-active .ql-stroke,
    .ql-toolbar button.ql-active .ql-fill {
        stroke: #60a5fa !important;
        fill: #60a5fa !important;
    }
</style>
@endpush

@section('content')
<div class="max-w-4xl">
    <form action="{{ route('admin.legal-pages.store') }}" method="POST">
        @csrf

        <div class="bg-dark-100 rounded-lg shadow-sm border border-dark-200 p-6">
            <div class="space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-300 mb-2">
                        Titre de la page <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" id="title"
                           value="{{ old('title') }}"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           required
                           placeholder="Ex: Conditions Générales d'Utilisation">
                    @error('title')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Slug -->
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-300 mb-2">
                        Slug (URL)
                    </label>
                    <div class="flex items-center">
                        <span class="px-3 py-2 bg-dark-50 border border-dark-300 border-r-0 rounded-l-lg text-gray-400 text-sm">
                            /legal/
                        </span>
                        <input type="text" name="slug" id="slug"
                               value="{{ old('slug') }}"
                               class="flex-1 px-4 py-2 bg-dark-50 border border-dark-300 rounded-r-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="Sera généré automatiquement si vide">
                    </div>
                    <p class="mt-1 text-xs text-gray-400">URL unique pour accéder à cette page. Laissez vide pour générer automatiquement depuis le titre.</p>
                    @error('slug')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Content -->
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-300 mb-2">
                        Contenu <span class="text-red-500">*</span>
                    </label>
                    <div id="editor-container"></div>
                    <input type="hidden" name="content" id="content" required>
                    <p class="mt-1 text-xs text-gray-400">Utilisez les outils de mise en forme ci-dessus pour rédiger le contenu</p>
                    @error('content')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Order -->
                    <div>
                        <label for="order" class="block text-sm font-medium text-gray-300 mb-2">
                            Ordre d'affichage
                        </label>
                        <input type="number" name="order" id="order" min="0"
                               value="{{ old('order', 0) }}"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <p class="mt-1 text-xs text-gray-400">Les pages seront affichées dans l'ordre croissant</p>
                    </div>

                    <!-- Active Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Statut
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-14 h-7 bg-dark-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-primary-500"></div>
                            <span class="ml-3 text-sm text-gray-300">Page active</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-400">Les pages inactives ne seront pas visibles publiquement</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between mt-6">
            <a href="{{ route('admin.legal-pages.index') }}"
               class="px-6 py-2 bg-dark-300 text-white rounded-lg hover:bg-dark-400 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Retour
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-md">
                <i class="fas fa-save mr-2"></i> Créer
            </button>
        </div>
    </form>
</div>

@push('scripts')
<!-- Quill Editor JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill editor
        var toolbarOptions = [
            [{ 'header': [1, 2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'indent': '-1'}, { 'indent': '+1' }],
            [{ 'align': [] }],
            [{ 'color': [] }, { 'background': [] }],
            ['link', 'blockquote', 'code-block'],
            ['clean']
        ];

        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: toolbarOptions
            },
            placeholder: 'Rédigez le contenu de votre page légale ici...'
        });

        // Charger le contenu initial si présent (pour old input)
        @if(old('content'))
            quill.root.innerHTML = {!! json_encode(old('content')) !!};
        @endif

        // Fonction pour mettre à jour le champ hidden
        function updateHiddenField() {
            var contentInput = document.getElementById('content');
            contentInput.value = quill.root.innerHTML;
            console.log('Contenu mis à jour:', contentInput.value.substring(0, 100) + '...');
        }

        // Mettre à jour à chaque changement de texte
        quill.on('text-change', function() {
            updateHiddenField();
        });

        // Mettre à jour aussi lors de la soumission du formulaire
        var form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            updateHiddenField();
        });

        // Initialiser le champ hidden au chargement (si contenu existant)
        @if(old('content'))
            updateHiddenField();
        @endif

        // Auto-generate slug from title
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        let manualSlugEdit = false;

        slugInput.addEventListener('input', function() {
            if (this.value !== '') {
                manualSlugEdit = true;
            }
        });

        titleInput.addEventListener('input', function() {
            if (!manualSlugEdit) {
                const slug = this.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                slugInput.value = slug;
            }
        });
    });
</script>
@endpush
@endsection
