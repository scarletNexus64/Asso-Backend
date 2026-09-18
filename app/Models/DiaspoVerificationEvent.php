<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace administrable du statut de vérification Diaspo (identité du voyageur et
 * publication de ses offres). Journal en ajout seul : on n'édite jamais une ligne.
 */
class DiaspoVerificationEvent extends Model
{
    public const UPDATED_AT = null;

    public const DOCUMENTS_SUBMITTED = 'documents_submitted';
    public const IDENTITY_VERIFIED = 'identity_verified';
    public const IDENTITY_REJECTED = 'identity_rejected';
    public const OFFER_PUBLISHED_UNVERIFIED = 'offer_published_unverified';
    public const OFFER_VERIFIED = 'offer_verified';
    public const OFFER_APPROVED_BY_ADMIN = 'offer_approved_by_admin';
    public const OFFER_REJECTED_BY_ADMIN = 'offer_rejected_by_admin';
    public const OFFER_DELETED_BY_ADMIN = 'offer_deleted_by_admin';
    public const DEADLINE_EXTENDED = 'deadline_extended';
    public const DEADLINE_REMINDER_SENT = 'deadline_reminder_sent';
    public const OFFER_REMOVED_DEADLINE = 'offer_removed_deadline';

    public const LABELS = [
        self::DOCUMENTS_SUBMITTED => 'Pièces d\'identité envoyées',
        self::IDENTITY_VERIFIED => 'Identité validée',
        self::IDENTITY_REJECTED => 'Pièces refusées',
        self::OFFER_PUBLISHED_UNVERIFIED => 'Offre publiée — profil non vérifié',
        self::OFFER_VERIFIED => 'Mention « Profil non vérifié » retirée',
        self::OFFER_APPROVED_BY_ADMIN => 'Offre validée par l\'administration',
        self::OFFER_REJECTED_BY_ADMIN => 'Offre rejetée par l\'administration',
        self::OFFER_DELETED_BY_ADMIN => 'Offre supprimée par l\'administration',
        self::DEADLINE_EXTENDED => 'Délai de régularisation prolongé',
        self::DEADLINE_REMINDER_SENT => 'Rappel d\'échéance envoyé',
        self::OFFER_REMOVED_DEADLINE => 'Offre retirée — délai de régularisation dépassé',
    ];

    protected $fillable = [
        'user_id', 'diaspo_offer_id', 'actor_id', 'event',
        'status_from', 'status_to', 'reason', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(DiaspoOffer::class, 'diaspo_offer_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }
}
