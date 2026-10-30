<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Contrôle d'un IBAN AVANT de proposer le virement automatique (Stripe Connect).
 *
 * Deux questions distinctes :
 * 1. L'IBAN est-il bien formé ? (longueur du pays + clé ISO 13616 « mod 97 »)
 * 2. Stripe peut-il y verser ? Stripe ne paie que vers certains pays : un IBAN
 *    camerounais (CEMAC), par exemple, est valide mais refusé. Dans ce cas le
 *    vendeur est orienté vers Mobile Money, ou vers un virement manuel (≈ 48 h).
 *
 * La liste des pays Stripe est réglable (`stripe_iban_countries`, codes séparés
 * par des virgules) ; par défaut : zone SEPA + Royaume-Uni + Suisse.
 */
class IbanValidator
{
    /** Longueur officielle de l'IBAN par pays (registre SWIFT + zone CEMAC/UEMOA). */
    public const LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16,
        'BG' => 22, 'BH' => 22, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24,
        'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18,
        'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IQ' => 23,
        'IS' => 26, 'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LC' => 32,
        'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22,
        'MK' => 19, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24,
        'PL' => 28, 'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24,
        'SC' => 31, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'ST' => 25, 'SV' => 28,
        'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VA' => 22, 'VG' => 24, 'XK' => 20,
        // Afrique centrale et de l'Ouest (IBAN nationaux, hors registre SWIFT).
        'CM' => 27, 'CG' => 27, 'GA' => 27, 'TD' => 27, 'CF' => 27, 'GQ' => 27,
        'BJ' => 28, 'BF' => 28, 'CI' => 28, 'ML' => 28, 'NE' => 28, 'SN' => 28, 'TG' => 28,
        'GW' => 25, 'MG' => 27, 'DZ' => 26, 'MA' => 28, 'AO' => 25, 'MZ' => 25, 'BI' => 27,
        'CV' => 25, 'KM' => 27, 'DJ' => 27,
    ];

    /**
     * IBAN nationaux hors registre SWIFT : la clé n'y suit pas toujours la norme
     * ISO 13616, on ne la contrôle donc pas (l'admin vérifie avant le virement manuel).
     */
    public const NATIONAL_FORMATS = [
        'CM', 'CG', 'GA', 'TD', 'CF', 'GQ', 'BJ', 'BF', 'CI', 'ML', 'NE', 'SN', 'TG', 'GW',
        'MG', 'DZ', 'MA', 'AO', 'MZ', 'BI', 'CV', 'KM', 'DJ',
    ];

    /** Zone CEMAC : RIB de 23 chiffres (banque 5, guichet 5, compte 11, clé 2). */
    public const CEMAC = ['CM', 'CG', 'GA', 'TD', 'CF', 'GQ'];

    /** Zone UEMOA : RIB de 24 caractères, le code banque commence par le pays (ex. BJ061). */
    public const UEMOA = ['BJ', 'BF', 'CI', 'ML', 'NE', 'SN', 'TG', 'GW'];

    /** Pays saisis en toutes lettres dans le profil → code ISO (indice pour un RIB CEMAC). */
    private const COUNTRY_NAMES = [
        'cameroun' => 'CM', 'cameroon' => 'CM', 'gabon' => 'GA', 'congo' => 'CG',
        'republique du congo' => 'CG', 'tchad' => 'TD', 'chad' => 'TD',
        'centrafrique' => 'CF', 'republique centrafricaine' => 'CF', 'central african republic' => 'CF',
        'guinee equatoriale' => 'GQ', 'equatorial guinea' => 'GQ',
    ];

    /** Pays où Stripe verse par défaut (zone SEPA + GB + CH). */
    public const DEFAULT_STRIPE_COUNTRIES = [
        'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR',
        'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT',
        'RO', 'SE', 'SI', 'SK',
    ];

    public static function normalize(?string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban));
    }

    /** IBAN groupé par 4 caractères (affichage). */
    public static function format(string $iban): string
    {
        $value = self::normalize($iban);
        if ($rib = self::detectRib($value)) {
            return $rib['formatted'];
        }

        return trim(chunk_split($value, 4, ' '));
    }

    /** @return string[] */
    public static function stripeCountries(): array
    {
        $configured = trim((string) Setting::get('stripe_iban_countries', ''));
        if ($configured === '') {
            return self::DEFAULT_STRIPE_COUNTRIES;
        }

        return array_values(array_filter(array_map(
            fn ($c) => strtoupper(trim($c)),
            explode(',', $configured)
        ), fn ($c) => preg_match('/^[A-Z]{2}$/', $c)));
    }

    /** Code pays ISO à partir du pays du profil (« Cameroun », « CM »…), sinon null. */
    public static function countryHint(?string $country): ?string
    {
        $value = trim((string) $country);
        if (preg_match('/^[A-Za-z]{2}$/', $value)) {
            return strtoupper($value);
        }
        $key = strtolower(\Illuminate\Support\Str::ascii($value));

        return self::COUNTRY_NAMES[$key] ?? null;
    }

    /**
     * RIB national saisi à la place d'un IBAN (courant en Afrique centrale et de
     * l'Ouest, où beaucoup de banques ne remettent pas d'IBAN).
     *
     * @return array{country:string, formatted:string}|null
     */
    public static function detectRib(string $value, ?string $countryHint = null): ?array
    {
        // CEMAC : 23 chiffres, le pays n'y figure pas → profil, sinon Cameroun.
        if (preg_match('/^(\d{5})(\d{5})(\d{11})(\d{2})$/', $value, $m)) {
            $country = in_array($countryHint, self::CEMAC, true) ? $countryHint : 'CM';

            return ['country' => $country, 'formatted' => "{$m[1]} {$m[2]} {$m[3]} {$m[4]}"];
        }

        // UEMOA : BJ061 01001 012345678901 23.
        if (preg_match('/^([A-Z]{2})(\d{3})(\d{5})(\d{12})(\d{2})$/', $value, $m)
            && in_array($m[1], self::UEMOA, true)) {
            return ['country' => $m[1], 'formatted' => "{$m[1]}{$m[2]} {$m[3]} {$m[4]} {$m[5]}"];
        }

        return null;
    }

    public static function checksumValid(string $iban): bool
    {
        $iban = self::normalize($iban);
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            foreach (str_split($digits) as $d) {
                $remainder = ($remainder * 10 + (int) $d) % 97;
            }
        }

        return $remainder === 1;
    }

    /**
     * @return array{valid:bool, iban:string, formatted:string, country:?string, last4:?string,
     *               stripe_supported:bool, reason:?string, message:string}
     */
    public static function check(?string $input, ?string $countryHint = null): array
    {
        $iban = self::normalize($input);
        $country = strlen($iban) >= 2 ? substr($iban, 0, 2) : null;

        $result = fn (bool $valid, ?string $reason, string $message, bool $stripe = false) => [
            'valid' => $valid,
            'kind' => 'iban',
            'iban' => $iban,
            'formatted' => self::format($iban),
            'country' => $country,
            'last4' => strlen($iban) >= 4 ? substr($iban, -4) : null,
            'stripe_supported' => $stripe,
            'reason' => $reason,
            'message' => $message,
        ];

        // RIB africain : accepté tel quel (virement manuel par ASSO, jamais Stripe).
        if ($rib = self::detectRib($iban, $countryHint)) {
            return [
                'valid' => true,
                'kind' => 'rib',
                'iban' => $iban,
                'formatted' => $rib['formatted'],
                'country' => $rib['country'],
                'last4' => substr($iban, -4),
                'stripe_supported' => false,
                'reason' => 'stripe_unsupported',
                'message' => __('wallet.iban.rib_accepted'),
            ];
        }

        if ($iban === '' || !preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban)) {
            return $result(false, 'format', __('wallet.iban.invalid_format'));
        }

        $expected = self::LENGTHS[$country] ?? null;
        if ($expected !== null && strlen($iban) !== $expected) {
            return $result(false, 'length', __('wallet.iban.invalid_length', [
                'country' => $country,
                'length' => $expected,
            ]));
        }

        if (!in_array($country, self::NATIONAL_FORMATS, true) && !self::checksumValid($iban)) {
            return $result(false, 'checksum', __('wallet.iban.invalid_checksum'));
        }

        if (!in_array($country, self::stripeCountries(), true)) {
            return $result(true, 'stripe_unsupported', __('wallet.iban.stripe_unsupported'));
        }

        return $result(true, null, __('wallet.iban.stripe_supported'), true);
    }
}
