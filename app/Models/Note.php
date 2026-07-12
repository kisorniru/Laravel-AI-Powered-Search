<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'title',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'embedded_at' => 'datetime',
        ];
    }

    public function textToEmbed(): string
    {
        return trim($this->title."\n\n".$this->body);
    }
}
