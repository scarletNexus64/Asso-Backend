<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PostLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'likeable_id',
        'likeable_type',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Boot method - tient à jour likes_count / dislikes_count de l'élément aimé.
     */
    protected static function booted()
    {
        static::created(function ($like) {
            static::adjust($like, $like->type, 1);
        });

        static::deleted(function ($like) {
            static::adjust($like, $like->type, -1);
        });

        static::updated(function ($like) {
            // Bascule like <-> dislike
            $oldType = $like->getOriginal('type');
            if ($oldType !== $like->type) {
                static::adjust($like, $oldType, -1);
                static::adjust($like, $like->type, 1);
            }
        });
    }

    private static function adjust(PostLike $like, ?string $type, int $delta): void
    {
        $column = $type === 'dislike' ? 'dislikes_count' : 'likes_count';
        $modelClass = $like->likeable_type;
        if ($type === 'dislike' && $modelClass !== Post::class) {
            return; // seuls les posts ont des « je n'aime pas »
        }
        if (!class_exists($modelClass)) {
            return;
        }

        $query = $modelClass::withTrashed()->whereKey($like->likeable_id);
        if ($delta > 0) {
            $query->increment($column, $delta);
        } else {
            $query->where($column, '>', 0)->decrement($column, -$delta);
        }
    }
}
