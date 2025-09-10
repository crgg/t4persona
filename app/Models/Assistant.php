<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'base_personality' => 'array',
        'date_creation'    => 'immutable_datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
