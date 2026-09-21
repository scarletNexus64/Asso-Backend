<?php

if (!function_exists('media_url')) {
    /**
     * URL publique d'un média stocké (image, logo, avatar, document).
     *
     * Le chemin en base est normalement relatif au disque public
     * (« products/xyz.jpg »), et l'on préfixe alors par APP_URL + /storage.
     * Mais certains enregistrements — les données de démonstration, les
     * imports — contiennent déjà une URL absolue : la préfixer donnerait
     * « http://host/storage/https://… », une adresse morte. On la renvoie
     * donc telle quelle.
     *
     * Un « storage/ » déjà présent en tête du chemin est retiré pour éviter
     * le doublon « /storage/storage/ ».
     *
     * @param string|null $path
     * @return string|null
     */
    function media_url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        // Déjà une adresse complète (http://, https://, //cdn…) : ne pas toucher.
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return asset('storage/' . $path);
    }
}
