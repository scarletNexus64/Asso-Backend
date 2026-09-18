<?php

namespace App\Support;

/**
 * Localisation lisible « Ville, Pays » (ex. « Douala, Cameroun »).
 *
 * Les anciennes boutiques n'ont qu'une adresse libre : « rue, ville, pays »,
 * le display_name complet de Nominatim, ou même « Lat: x, Lng: y ». On en
 * extrait au mieux la ville et le pays.
 */
class LocationFormatter
{
    /** Villes reconnues dans une adresse, avec leur pays (le pays manque souvent). */
    private const KNOWN_CITIES = [
        'Douala' => 'Cameroun', 'Yaoundé' => 'Cameroun', 'Yaounde' => 'Cameroun',
        'Bafoussam' => 'Cameroun', 'Garoua' => 'Cameroun', 'Bamenda' => 'Cameroun',
        'Maroua' => 'Cameroun', 'Ngaoundéré' => 'Cameroun', 'Ngaoundere' => 'Cameroun',
        'Bertoua' => 'Cameroun', 'Kribi' => 'Cameroun', 'Limbé' => 'Cameroun', 'Limbe' => 'Cameroun',
        'Buea' => 'Cameroun', 'Ebolowa' => 'Cameroun', 'Kumba' => 'Cameroun', 'Edéa' => 'Cameroun',
        'Edea' => 'Cameroun', 'Dschang' => 'Cameroun', 'Nkongsamba' => 'Cameroun', 'Foumban' => 'Cameroun',
        'Cotonou' => 'Bénin', 'Porto-Novo' => 'Bénin', 'Lomé' => 'Togo', 'Lome' => 'Togo',
        'Abidjan' => "Côte d'Ivoire", 'Dakar' => 'Sénégal', 'Libreville' => 'Gabon',
        'Brazzaville' => 'Congo', 'Kinshasa' => 'RD Congo', 'Lagos' => 'Nigeria', 'Abuja' => 'Nigeria',
        'Paris' => 'France', 'Dubaï' => 'Émirats arabes unis', 'Dubai' => 'Émirats arabes unis',
        'Istanbul' => 'Turquie', 'Guangzhou' => 'Chine', 'Shanghai' => 'Chine',
    ];

    /** Un dernier segment qui n'est pas un pays connu n'est pas pris pour un pays. */
    private const KNOWN_COUNTRIES = [
        'cameroun', 'cameroon', 'bénin', 'benin', 'togo', "côte d'ivoire", 'cote d\'ivoire', 'sénégal',
        'senegal', 'gabon', 'congo', 'rd congo', 'nigeria', 'tchad', 'chad', 'france', 'belgique',
        'canada', 'chine', 'china', 'turquie', 'turkey', 'émirats arabes unis', 'united arab emirates',
        'guinée équatoriale', 'centrafrique', 'mali', 'burkina faso', 'niger', 'ghana',
    ];

    public static function label(?string $city, ?string $country, ?string $address = null): ?string
    {
        $city = self::clean($city);
        $country = self::clean($country);

        if ($city === null && $country === null && $address !== null) {
            [$city, $country] = self::parse($address);
        }

        $label = implode(', ', array_filter([$city, $country]));
        return $label !== '' ? $label : null;
    }

    /** @return array{0: ?string, 1: ?string} [ville, pays] */
    public static function parse(?string $address): array
    {
        if ($address === null || preg_match('/\bLat\s*:/i', $address)) {
            return [null, null];
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $address)),
            // Ignore les segments vides, codes postaux et numéros de rue isolés.
            fn (string $part) => $part !== '' && !preg_match('/^\d[\d\s-]*$/', $part),
        ));

        if ($parts === []) {
            return [null, null];
        }

        $last = end($parts);
        $country = in_array(mb_strtolower($last), self::KNOWN_COUNTRIES, true) ? $last : null;

        // 1. Une ville connue n'importe où dans l'adresse (y compris en dernier).
        foreach ($parts as $part) {
            foreach (self::KNOWN_CITIES as $known => $knownCountry) {
                if (mb_stripos($part, $known) !== false) {
                    return [$known, $country ?? $knownCountry];
                }
            }
        }

        // 2. Format court « …, ville, pays » avec un pays reconnu.
        if ($country !== null && count($parts) >= 2) {
            return [count($parts) <= 3 ? $parts[count($parts) - 2] : null, $country];
        }

        return [null, $country];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
