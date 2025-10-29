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
        'assistant_image_response',
        'emotion_deteted',
        'timestamp',
        'user_audio_url',
        'has_response',
        'was_canceled',
        'file_uuid',
        'file_respond'
    ];

    protected $casts = [
        'timestamp' => 'immutable_datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GeneratedSession::class, 'session_id');
    }

}
