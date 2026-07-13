<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'embedded_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->where('is_public', true);
        }

        return $query->where('user_id', $user->id);
    }

    public function textToEmbed(): string
    {
        $visibility = $this->is_public ? 'Public' : 'Private';
        $author = $this->user?->name ?? 'Unknown author';

        return implode("\n\n", [
            'Title: '.$this->title,
            "Body:\n".$this->body,
            'Visibility: '.$visibility,
            'Author: '.$author,
        ]);
    }
}
