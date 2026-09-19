<?php

namespace App\Support;

/**
 * Pays des trajets de livraison : les boutiques stockent un nom libre
 * (« Cameroun », « Cameroon », « Dubaï »…), les trajets un code ISO à 2 lettres.
 */
class CountryCode
{
    public const NAMES = [
        'CM' => 'Cameroun',
        'CN' => 'Chine',
        'AE' => 'Émirats arabes unis (Dubaï)',
        'TR' => 'Turquie',
        'FR' => 'France',
        'BE' => 'Belgique',
        'DE' => 'Allemagne',
        'GB' => 'Royaume-Uni',
        'US' => 'États-Unis',
        'CA' => 'Canada',
        'NG' => 'Nigeria',
        'GA' => 'Gabon',
        'TD' => 'Tchad',
        'CG' => 'Congo',
        'GQ' => 'Guinée équatoriale',
        'CF' => 'Centrafrique',
        'BJ' => 'Bénin',
        'TG' => 'Togo',
        'CI' => "Côte d'Ivoire",
        'SN' => 'Sénégal',
    ];

    private const ALIASES = [
        'cameroun' => 'CM', 'cameroon' => 'CM', 'kamerun' => 'CM',
        'chine' => 'CN', 'china' => 'CN',
        'emiratsarabesunis' => 'AE', 'unitedarabemirates' => 'AE', 'dubai' => 'AE', 'uae' => 'AE', 'eau' => 'AE',
        'turquie' => 'TR', 'turkey' => 'TR', 'turkiye' => 'TR',
        'france' => 'FR', 'belgique' => 'BE', 'belgium' => 'BE', 'allemagne' => 'DE', 'germany' => 'DE',
        'royaumeuni' => 'GB', 'unitedkingdom' => 'GB', 'uk' => 'GB',
        'etatsunis' => 'US', 'unitedstates' => 'US', 'usa' => 'US', 'canada' => 'CA',
        'nigeria' => 'NG', 'gabon' => 'GA', 'tchad' => 'TD', 'chad' => 'TD', 'congo' => 'CG',
        'guineeequatoriale' => 'GQ', 'centrafrique' => 'CF', 'benin' => 'BJ', 'togo' => 'TG',
        'cotedivoire' => 'CI', 'senegal' => 'SN',
    ];

    /** Code ISO à 2 lettres depuis un code ou un nom de pays, null si inconnu. */
    public static function normalize(?string $country): ?string
    {
        if ($country === null || trim($country) === '') {
            return null;
        }

        $upper = strtoupper(trim($country));
        if (strlen($upper) === 2 && isset(self::NAMES[$upper])) {
            return $upper;
        }

        return self::ALIASES[self::key($country)] ?? null;
    }

    public static function name(?string $code): ?string
    {
        return $code ? (self::NAMES[strtoupper($code)] ?? strtoupper($code)) : null;
    }

    /** Nom de ville comparable : sans accents, casse ni ponctuation (« Yaoundé » = « YAOUNDE »). */
    public static function key(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return preg_replace('/[^a-z0-9]/', '', strtolower($ascii));
    }

    public static function sameCity(?string $a, ?string $b): bool
    {
        if (!$a || !$b) {
            return false;
        }

        return self::key($a) === self::key($b);
    }
}
