<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasFactory;

    protected $table = 'device_tokens';

    protected $fillable = [
        'user_id',
        'PHONEFROM',
        'version',
        'device_token'
    ];

    public static function save_device_token(int $userId, string $deviceToken, ?string $phoneFrom = null, ?string $version = null): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId],
            [
                'device_token' => $deviceToken,
                'PHONEFROM'    => $phoneFrom,
                'version'      => $version,
            ]
        );
    }

}
