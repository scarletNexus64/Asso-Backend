<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LegalPage extends Model
{
    use HasTranslations;

    /**
     * Documents que l'utilisateur doit accepter avant une action dans l'application.
     * contexte => [libellé, slugs des pages requises].
     *
     * Filtre de l'API : GET /api/v1/legal-pages?context=registration
     */
    public const CONTEXTS = [
        'registration' => [
            'label' => 'Création de compte',
            'slugs' => ['conditions-generales-utilisation', 'charte-confiance-securite'],
        ],
        'purchase' => [
            'label' => 'Achat',
            'slugs' => ['conditions-generales-achat'],
        ],
        'vendor' => [
            'label' => 'Création du compte vendeur',
            'slugs' => ['charte-vendeurs'],
        ],
        'delivery_partner' => [
            'label' => 'Inscription partenaire livreur',
            'slugs' => ['charte-livreurs-partenaires'],
        ],
    ];

    /** Champs traduisibles (voir HasTranslations). */
    protected array $translatable = ['title', 'content'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
        'order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope pour récupérer uniquement les pages actives.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour ordonner les pages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order', 'asc')->orderBy('title', 'asc');
    }

    /**
     * Récupérer une page par son slug.
     *
     * @param string $slug
     * @return LegalPage|null
     */
    public static function findBySlug(string $slug): ?LegalPage
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Récupérer une page active par son slug.
     *
     * @param string $slug
     * @return LegalPage|null
     */
    public static function findActiveBySlug(string $slug): ?LegalPage
    {
        return self::active()->where('slug', $slug)->first();
    }

    /**
     * Récupérer toutes les pages actives ordonnées.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive()
    {
        return self::active()->ordered()->get();
    }

    /**
     * Contextes où la page doit être acceptée (registration, purchase…).
     *
     * @return array<int, string>
     */
    public static function contextsFor(string $slug): array
    {
        return array_keys(array_filter(self::CONTEXTS, fn ($context) => in_array($slug, $context['slugs'], true)));
    }

    /** Vrai quand l'application s'appuie sur ce slug : il ne doit pas changer. */
    public static function isRequiredSlug(string $slug): bool
    {
        return self::contextsFor($slug) !== [];
    }

    /**
     * Générer automatiquement un slug à partir du titre.
     *
     * @param string $title
     * @return string
     */
    public static function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

        // Vérifier l'unicité du slug
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Boot method.
     */
    protected static function boot()
    {
        parent::boot();

        // Générer automatiquement le slug si non fourni
        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = self::generateSlug($page->title);
            }
        });
    }
}
