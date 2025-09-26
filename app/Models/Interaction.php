<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $table = 'interactions';
    public $timestamps = false;

    public $incrementing = false; // UUID PK
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'session_id',
        'text_from_user',
        'assistant_text_response',
        'assistant_audio_response',
        'emotion_deteted',    // (tal cual está escrito en el doc)
        'timestamp',
        'file_uuid',
    ];

    protected $casts = [
        'timestamp' => 'immutable_datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GeneratedSession::class, 'session_id');
    }

}
