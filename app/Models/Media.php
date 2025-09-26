<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';
    public $timestamps = false;

    public $incrementing = false; // UUID
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'assistant_id',
        'type',
        'storage_url',
        'transcription',
        'metadata',
        'extra_fields',
        'extra_fields_two',
        'date_upload',
        'whatsapp_media_file_id'
    ];

    protected $casts = [
        'metadata'    => 'array',
        'date_upload' => 'immutable_datetime',
    ];
}
