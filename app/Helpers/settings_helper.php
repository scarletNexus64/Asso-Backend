<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    /**
     * Récupérer la valeur d'un paramètre.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('settings_by_group')) {
    /**
     * Récupérer tous les paramètres d'un groupe.
     *
     * @param string $group
     * @return array
     */
    function settings_by_group(string $group): array
    {
        return Setting::getByGroup($group);
    }
}
