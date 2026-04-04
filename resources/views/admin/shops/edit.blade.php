@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('admin.shops.index') }}"
               class="text-gray-400 hover:text-primary-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold text-white">Modifier la Boutique</h1>
        </div>
        <p class="text-gray-400 ml-10">Modifiez les informations de {{ $shop->name }}</p>
    </div>

    <!-- Form -->
    <div class="bg-dark-100 rounded-xl shadow-lg p-6">
        <form action="{{ route('admin.shops.update', $shop) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <!-- User Selection -->
                <div>
                    <label for="user_id" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user text-primary-500 mr-1"></i>
                        Propriétaire <span class="text-red-500">*</span>
                    </label>
                    <select name="user_id"
                            id="user_id"
                            required
                            class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white @error('user_id') border-red-500 @enderror">
                        <option value="">Sélectionnez un utilisateur</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('user_id', $shop->user_id) == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} - {{ ucfirst($user->role) }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Shop Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-store text-primary-500 mr-1"></i>
                        Nom de la boutique <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name', $shop->name) }}"
                           required
                           placeholder="Ex: Boutique de Marie"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white placeholder-gray-400 @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">
                        Slug actuel: <span class="font-mono font-semibold">{{ $shop->slug }}</span>
                        (sera mis à jour si le nom change)
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-align-left text-primary-500 mr-1"></i>
                        Description
                    </label>
                    <textarea name="description"
                              id="description"
                              rows="4"
                              placeholder="Décrivez l'activité de la boutique..."
                              class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white placeholder-gray-400 @error('description') border-red-500 @enderror">{{ old('description', $shop->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Shop Link -->
                <div>
                    <label for="shop_link" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-link text-primary-500 mr-1"></i>
                        Lien de la boutique
                    </label>
                    <input type="url"
                           name="shop_link"
                           id="shop_link"
                           value="{{ old('shop_link', $shop->shop_link) }}"
                           placeholder="https://exemple.com/ma-boutique"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white placeholder-gray-400 @error('shop_link') border-red-500 @enderror">
                    @error('shop_link')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">URL externe vers la boutique (optionnel)</p>
                </div>

                <!-- Logo -->
                <div>
                    <label for="logo" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-image text-primary-500 mr-1"></i>
                        Logo de la boutique
                    </label>

                    @if($shop->logo_url)
                        <div class="mb-3">
                            <p class="text-sm text-gray-400 mb-2">Logo actuel:</p>
                            <img src="{{ $shop->logo_url }}"
                                 alt="Logo de {{ $shop->name }}"
                                 class="h-32 w-32 object-cover rounded-lg border-2 border-dark-300"
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<p class=\'text-sm text-red-400\'>Logo introuvable</p>';">
                        </div>
                    @endif

                    <input type="file"
                           name="logo"
                           id="logo"
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white @error('logo') border-red-500 @enderror"
                           onchange="previewLogo(event)">
                    @error('logo')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">
                        Formats acceptés: JPEG, PNG, GIF, WebP. Taille max: 2MB
                        @if($shop->logo_url)
                            <br><span class="text-primary-600">Laisser vide pour conserver le logo actuel</span>
                        @endif
                    </p>

                    <!-- Logo Preview -->
                    <div id="logoPreview" class="mt-3 hidden">
                        <p class="text-sm text-gray-400 mb-2">Nouveau logo:</p>
                        <img id="previewImage" src="" alt="Aperçu du logo" class="h-32 w-32 object-cover rounded-lg border-2 border-orange-500">
                    </div>
                </div>

                <!-- Localisation with Google Maps -->
                <div>
                    @include('admin.partials.google-map', [
                        'id' => 'shop-map',
                        'label' => 'Localisation de la boutique',
                        'latitude' => old('latitude', $shop->latitude),
                        'longitude' => old('longitude', $shop->longitude),
                        'address' => old('address', $shop->address),
                        'zoom' => 15
                    ])
                </div>

                <!-- Verification Status -->
                <div>
                    <label class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-certificate text-primary-500 mr-1"></i>
                        Statut de vérification <span class="text-red-500">*</span>
                    </label>

                    <!-- Current Status Info -->
                    @if($shop->isVerified())
                        <div class="mb-3 p-3 bg-green-500/20 border border-green-500/50 rounded-lg">
                            <p class="text-green-300 text-sm">
                                <i class="fas fa-check-circle mr-1"></i>
                                Vérifiée le {{ $shop->verified_at->format('d/m/Y à H:i') }}
                                @if($shop->verifier)
                                    par <span class="font-semibold">{{ $shop->verifier->name }}</span>
                                @endif
                            </p>
                        </div>
                    @elseif($shop->isRejected())
                        <div class="mb-3 p-3 bg-red-500/20 border border-red-500/50 rounded-lg">
                            <p class="text-red-300 text-sm mb-1">
                                <i class="fas fa-times-circle mr-1"></i>
                                Rejetée le {{ $shop->rejected_at->format('d/m/Y à H:i') }}
                                @if($shop->rejector)
                                    par <span class="font-semibold">{{ $shop->rejector->name }}</span>
                                @endif
                            </p>
                            @if($shop->rejection_reason)
                                <p class="text-red-400 text-xs mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Raison: {{ $shop->rejection_reason }}
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="mb-3 p-3 bg-orange-500/20 border border-orange-500/50 rounded-lg">
                            <p class="text-orange-300 text-sm">
                                <i class="fas fa-clock mr-1"></i>
                                En attente de vérification
                            </p>
                        </div>
                    @endif

                    <div class="flex flex-col gap-3">
                        <label class="flex items-center cursor-pointer p-3 bg-dark-50 border border-dark-300 rounded-lg hover:bg-dark-200 transition-all">
                            <input type="radio"
                                   name="verification_status"
                                   value="pending"
                                   {{ old('verification_status', $shop->isPending() ? 'pending' : ($shop->isVerified() ? 'verified' : 'rejected')) == 'pending' ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary-600 border-dark-300 focus:ring-primary-500 verification-radio">
                            <span class="ml-2 text-white">
                                <i class="fas fa-clock text-orange-400 mr-1"></i>
                                En attente
                            </span>
                        </label>

                        <label class="flex items-center cursor-pointer p-3 bg-dark-50 border border-dark-300 rounded-lg hover:bg-dark-200 transition-all">
                            <input type="radio"
                                   name="verification_status"
                                   value="verified"
                                   {{ old('verification_status', $shop->isPending() ? 'pending' : ($shop->isVerified() ? 'verified' : 'rejected')) == 'verified' ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary-600 border-dark-300 focus:ring-primary-500 verification-radio">
                            <span class="ml-2 text-white">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                Vérifiée
                            </span>
                        </label>

                        <label class="flex items-center cursor-pointer p-3 bg-dark-50 border border-dark-300 rounded-lg hover:bg-dark-200 transition-all">
                            <input type="radio"
                                   name="verification_status"
                                   value="rejected"
                                   {{ old('verification_status', $shop->isPending() ? 'pending' : ($shop->isVerified() ? 'verified' : 'rejected')) == 'rejected' ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary-600 border-dark-300 focus:ring-primary-500 verification-radio">
                            <span class="ml-2 text-white">
                                <i class="fas fa-times-circle text-red-500 mr-1"></i>
                                Rejetée
                            </span>
                        </label>
                    </div>

                    <!-- Rejection Reason Field (shown only if rejected) -->
                    <div id="rejectionReasonField" class="mt-3 {{ old('verification_status', $shop->isPending() ? 'pending' : ($shop->isVerified() ? 'verified' : 'rejected')) == 'rejected' ? '' : 'hidden' }}">
                        <label for="rejection_reason" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-comment text-red-500 mr-1"></i>
                            Raison du rejet <span class="text-red-500">*</span>
                        </label>
                        <textarea name="rejection_reason"
                                  id="rejection_reason"
                                  rows="3"
                                  placeholder="Expliquez pourquoi cette boutique est rejetée..."
                                  class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-white placeholder-gray-400 @error('rejection_reason') border-red-500 @enderror">{{ old('rejection_reason', $shop->rejection_reason) }}</textarea>
                        @error('rejection_reason')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('verification_status')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-toggle-on text-primary-500 mr-1"></i>
                        Statut d'activité <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio"
                                   name="status"
                                   value="active"
                                   {{ old('status', $shop->status) == 'active' ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary-600 border-dark-300 focus:ring-primary-500">
                            <span class="ml-2 text-white">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                Active
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio"
                                   name="status"
                                   value="inactive"
                                   {{ old('status', $shop->status) == 'inactive' ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary-600 border-dark-300 focus:ring-primary-500">
                            <span class="ml-2 text-white">
                                <i class="fas fa-times-circle text-gray-400 mr-1"></i>
                                Inactive
                            </span>
                        </label>
                    </div>
                    @error('status')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Le statut d'activité est différent du statut de vérification. Une boutique peut être vérifiée mais inactive.
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex gap-4">
                <button type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>
                    Mettre à jour
                </button>
                <a href="{{ route('admin.shops.index') }}"
                   class="px-6 py-3 bg-gray-200 text-white rounded-lg hover:bg-gray-700 transition-all">
                    <i class="fas fa-times mr-2"></i>
                    Annuler
                </a>
            </div>
        </form>
    </div>

    <!-- Shop Info -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-blue-900/20 border-l-4 border-blue-500 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-box text-blue-500 text-xl mr-3"></i>
                <div>
                    <h4 class="font-semibold text-blue-800">Produits</h4>
                    <p class="text-blue-700 text-sm">
                        Cette boutique contient <span class="font-bold">{{ $shop->products->count() }} produit(s)</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-green-900/20 border-l-4 border-green-500 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-calendar text-green-500 text-xl mr-3"></i>
                <div>
                    <h4 class="font-semibold text-green-800">Date de création</h4>
                    <p class="text-green-700 text-sm">
                        Créée le <span class="font-bold">{{ $shop->created_at->format('d/m/Y à H:i') }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function previewLogo(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImage').src = e.target.result;
            document.getElementById('logoPreview').classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}

// Add event listeners to all verification status radio buttons
document.addEventListener('DOMContentLoaded', function() {
    const verificationRadios = document.querySelectorAll('.verification-radio');
    const rejectionReasonField = document.getElementById('rejectionReasonField');

    verificationRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'rejected' && this.checked) {
                rejectionReasonField.classList.remove('hidden');
            } else {
                rejectionReasonField.classList.add('hidden');
            }
        });
    });
});
</script>
@endpush
@endsection
