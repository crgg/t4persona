<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assistant extends Model
{
    protected $table = 'assistants';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'state',
        'base_personality',
        'age',
        'avatar_path',
        'family_relationship',
        'alias',
        'country',
        'language'
    ];

    protected $casts = [
        'base_personality' => 'array',
        'date_creation'    => 'immutable_datetime',
        'age'    => 'integer',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(\App\Models\GeneratedSession::class, 'assistant_id');
    }

    public function openSession(): HasOne
    {
        return $this->hasOne(\App\Models\GeneratedSession::class, 'assistant_id')
            ->whereNull('date_end');
    }

    public function interactions()
    {
        return $this->hasManyThrough(
            Interaction::class,         // Modelo final
            GeneratedSession::class,    // Modelo intermedio
            'assistant_id',             // FK en sesiones que apunta a assistant
            'session_id',               // FK en interactions que apunta a sesiones
            'id',                       // PK local (assistant)
            'id'                        // PK del intermedio (sessions)
        );
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

}
