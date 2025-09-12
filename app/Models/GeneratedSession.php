<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedSession extends Model
{
    protected $table = 'sessions';
    public $timestamps = false;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'assistant_id',
        'date_start',
        'date_end',
        'canal',
    ];

    protected $casts = [
        'date_start' => 'immutable_datetime',
        'date_end'   => 'immutable_datetime',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class, 'assistant_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'session_id');
    }

}
