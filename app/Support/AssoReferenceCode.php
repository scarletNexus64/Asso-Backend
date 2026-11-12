<?php

namespace App\Support;

/**
 * Codes de référence ASSO saisis dans les informations commerciales
 * (« Référence : ASSO-FRN-LRG-001 », « FR : Référence : FRN-CHN-ELM-001 »…).
 *
 * Les codes sont conservés tels que fournis : seuls les espaces et les
 * caractères invisibles collés au code sont retirés.
 */
final class AssoReferenceCode
{
    /** Espaces de largeur nulle, joint de mots, BOM. */
    private const INVISIBLE = '[\x{200B}-\x{200D}\x{2060}\x{FEFF}]*';

    /** Tirets saisis en remplacement du trait d'union (–, —, ‐, ‑, −). */
    private const DASHES = '[\x{2010}\x{2011}\x{2013}\x{2014}\x{2212}]';

    /** Un trait d'union ou l'un des tirets ci-dessus. */
    private const DASH = '[-\x{2010}\x{2011}\x{2013}\x{2014}\x{2212}]';

    private const CODE = '(?<![\p{L}\p{N}])(?:ASSO\s*'.self::DASH.'\s*)?FRN\s*'.self::DASH.'\s*[A-Z]+(?:\s*'.self::DASH.'\s*[A-Z]+)?\s*'.self::DASH.'\s*[0-9]+(?![\p{N}])';

    /** @return list<string> codes distincts trouvés dans le texte */
    public static function extract(?string $text): array
    {
        preg_match_all('/'.self::CODE.'/iu', self::prepare($text), $matches);

        return array_values(array_unique(array_map(self::normalize(...), $matches[0])));
    }

    /** Retire la mention « Référence : CODE » sans toucher au reste du texte. */
    public static function strip(?string $text): string
    {
        $text = (string) $text;
        // Préfixe éventuel (puce, « FR : »), libellé, code et point final.
        $segment = '/(?:^[ \t]*(?:[-•][ \t]*)?(?:FR[ \t]*:[ \t]*)?)?(?:R[ée]f[ée]rence[ \t]*:[ \t]*)?'
            .self::INVISIBLE.self::CODE.self::INVISIBLE.'\.?[ \t]*/imu';
        $text = preg_replace($segment, '', $text);
        $text = preg_replace('/[ \t]+(\R)/u', '$1', $text);
        $text = preg_replace('/^(?:[ \t]*\R)+/u', '', $text);
        $text = preg_replace('/(\R[ \t]*){3,}/u', "\r\n\r\n", $text);

        return trim($text);
    }

    /** Forme canonique d'un code : majuscules, sans espace ni caractère invisible. */
    public static function normalize(string $code): string
    {
        $code = preg_replace('/'.self::DASHES.'/u', '-', $code);

        return mb_strtoupper(preg_replace('/[\s\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00A0}]+/u', '', $code));
    }

    private static function prepare(?string $text): string
    {
        return preg_replace('/'.self::INVISIBLE.'/u', '', (string) $text);
    }
}
