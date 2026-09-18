{{-- Sélecteur de période. Attend $period (payload du service), $route, $routeParams (array), $extra (array de query). --}}
<div class="flex flex-wrap gap-2">
    @foreach($period['available'] as $option)
        <a href="{{ route($route, array_merge($routeParams ?? [], $extra ?? [], ['period' => $option['key']])) }}"
           class="px-3 py-2 text-sm rounded-lg border transition-all {{ $option['key'] === $period['key'] ? 'bg-primary-600 border-primary-500 text-white' : 'bg-dark-100 border-dark-200 text-gray-300 hover:bg-dark-200' }}">
            {{ $option['label'] }}
        </a>
    @endforeach
</div>
