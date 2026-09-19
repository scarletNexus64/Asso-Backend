<?php

namespace App\Models;

use App\Support\CountryCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grille urbaine zone à zone d'un partenaire dans une ville (ex. SOLEX Douala).
 * Le prix dépend du véhicule et du couple zone de départ / zone d'arrivée
 * (symétrique : Zone 1 → Zone 3 = Zone 3 → Zone 1). Le poids du colis limite les
 * véhicules proposés (max_weight_kg).
 */
class DeliveryCityGrid extends Model
{
    protected $fillable = [
        'deliverer_company_id',
        'city',
        'country',
        'zones',
        'vehicles',
        'agency_zone',
        'asso_commission',
        'is_active',
    ];

    protected $casts = [
        'zones' => 'array',
        'vehicles' => 'array',
        'agency_zone' => 'integer',
        'asso_commission' => 'float',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(DelivererCompany::class, 'deliverer_company_id');
    }

    public function coversCity(?string $city): bool
    {
        return CountryCode::sameCity($this->city, $city);
    }

    /** @return array{code: int, label: string, quarters: array}|null */
    public function zone(?int $code): ?array
    {
        return collect($this->zones)->firstWhere('code', $code);
    }

    /** « Zone 1 (Bali, Bonapriso, Bonanjo…) » */
    public function zoneLabel(int $code, int $maxQuarters = 3): string
    {
        $zone = $this->zone($code);
        if (!$zone) {
            return "Zone {$code}";
        }
        $quarters = $zone['quarters'] ?? [];
        $shown = implode(', ', array_slice($quarters, 0, $maxQuarters)) . (count($quarters) > $maxQuarters ? '…' : '');

        return $zone['label'] . ($shown !== '' ? " ({$shown})" : '');
    }

    /**
     * Zone d'un quartier, d'un code (« 3 », « Zone 3 ») ou d'une adresse libre qui cite
     * un quartier (« Rue 1.234, Akwa, Douala »). Le nom le plus long l'emporte
     * (« Akwa-Nord » avant « Akwa »).
     */
    public function zoneFor(?string $text): ?int
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (preg_match('/^\s*(?:zone\s*)?(\d{1,2})\s*$/i', $text, $m) && $this->zone((int) $m[1])) {
            return (int) $m[1];
        }

        $haystack = ' ' . preg_replace('/[^a-z0-9]+/', ' ', self::normalize($text)) . ' ';
        $best = null;
        $bestLength = 0;
        foreach ($this->zones as $zone) {
            foreach ($zone['quarters'] ?? [] as $quarter) {
                $needle = trim(preg_replace('/[^a-z0-9]+/', ' ', self::normalize($quarter)));
                if ($needle !== '' && str_contains($haystack, " {$needle} ") && strlen($needle) > $bestLength) {
                    $best = (int) $zone['code'];
                    $bestLength = strlen($needle);
                }
            }
        }

        return $best;
    }

    /** @return array{code: string, label: string, max_weight_kg: ?float, lead_time: ?string, prices: array}|null */
    public function vehicle(?string $code): ?array
    {
        return collect($this->vehicles)->firstWhere('code', $code);
    }

    /** Prix HT d'un véhicule entre deux zones (grille symétrique), null si non renseigné. */
    public function price(string $vehicle, int $from, int $to): ?float
    {
        $prices = $this->vehicle($vehicle)['prices'] ?? [];
        $key = min($from, $to) . '-' . max($from, $to);

        return isset($prices[$key]) && $prices[$key] !== '' ? (float) $prices[$key] : null;
    }

    /** Véhicules capables de porter ce poids, du plus léger au plus lourd. */
    public function vehiclesFor(float $weightKg): array
    {
        return collect($this->vehicles)
            ->filter(fn ($v) => empty($v['max_weight_kg']) || $weightKg <= (float) $v['max_weight_kg'])
            ->values()
            ->all();
    }

    /** Quartiers proposés à l'acheteur, par zone. */
    public function quarterOptions(): array
    {
        return collect($this->zones)->map(fn ($z) => [
            'zone' => (int) $z['code'],
            'label' => $z['label'],
            'quarters' => array_values($z['quarters'] ?? []),
        ])->values()->all();
    }

    private static function normalize(string $value): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value);
    }
}
