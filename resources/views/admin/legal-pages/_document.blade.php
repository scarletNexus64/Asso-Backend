{{-- Import d'un document Word : son texte remplace le contenu de l'éditeur. --}}
<div class="rounded-lg border border-dark-200 bg-dark-50 p-4">
    <label class="block text-sm font-medium text-gray-300 mb-1">
        <i class="fas fa-file-word text-blue-400 mr-1"></i> Importer un document Word (.docx)
    </label>
    <p class="text-xs text-gray-400 mb-3">
        Facultatif. Le texte du document remplace le contenu de l'éditeur ci-dessous
        @if(!empty($documents))
            (document actuel : <a href="{{ $documents[0]['url'] }}" download class="text-blue-400 hover:text-blue-300">{{ $documents[0]['name'] }}</a>).
            L'ancien texte et les anciens fichiers sont conservés.
        @else
            .
        @endif
    </p>
    <input type="file" name="document" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
    @error('document')
        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
    @enderror
</div>
