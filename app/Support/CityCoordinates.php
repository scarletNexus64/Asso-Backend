<?php

namespace App\Support;

/**
 * Position des principales villes du Cameroun, pour situer les agences et trajets
 * sur les cartes de l'admin. Une ville absente est recherchée côté navigateur (OpenStreetMap).
 */
class CityCoordinates
{
    private const CITIES = [
        'douala' => [4.0511, 9.7679],
        'yaounde' => [3.8480, 11.5021],
        'bafoussam' => [5.4781, 10.4176],
        'bamenda' => [5.9631, 10.1591],
        'bertoua' => [4.5774, 13.6846],
        'ngaoundere' => [7.3277, 13.5847],
        'garoua' => [9.3014, 13.3977],
        'maroua' => [10.5956, 14.3247],
        'nkongsamba' => [4.9547, 9.9404],
        'limbe' => [4.0240, 9.2149],
        'buea' => [4.1550, 9.2310],
        'kribi' => [2.9400, 9.9100],
        'ebolowa' => [2.9000, 11.1500],
        'edea' => [3.8000, 10.1333],
        'dschang' => [5.4500, 10.0667],
        'kumba' => [4.6363, 9.4469],
        'foumban' => [5.7270, 10.9000],
        'mbouda' => [5.6260, 10.2540],
        'sangmelima' => [2.9333, 11.9833],
        'kousseri' => [12.0769, 15.0306],
        'mbalmayo' => [3.5167, 11.5000],
        'loum' => [4.7182, 9.7351],
        'batouri' => [4.4333, 14.3667],
        'meiganga' => [6.5167, 14.3000],
        'tibati' => [6.4667, 12.6333],
        'bafang' => [5.1500, 10.1833],
        'bangangte' => [5.1500, 10.5167],
        'kumbo' => [6.2000, 10.6667],
        'wum' => [6.3833, 10.0667],
        'yagoua' => [10.3333, 15.2333],
        'mokolo' => [10.7400, 13.8000],
        'guider' => [9.9333, 13.9500],
        'abongmbang' => [3.9833, 13.1833],
        'obala' => [4.1667, 11.5333],
        'eseka' => [3.6500, 10.7667],
        'tiko' => [4.0750, 9.3600],
        'mamfe' => [5.7667, 9.2833],
    ];

    /** @return array{0: float, 1: float}|null */
    public static function of(?string $city): ?array
    {
        return $city ? (self::CITIES[CountryCode::key($city)] ?? null) : null;
    }
}
