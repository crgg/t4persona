<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversation';

    protected $fillable = [
        'id',
        'assistant_id',
        'zip_aws_path',
        'conversation',
        'metadata'
    ];


    protected $casts = [
        'conversation' => 'array',
        'metadata'     => 'array',
    ];


}
