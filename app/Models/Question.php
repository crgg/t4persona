<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';

    protected $fillable = [
        'instrument',   // 'BFI-44', 'TIPI-10', etc.
        'position',     // 1..44
        'dimension',    // openness|conscientiousness|extraversion|agreeableness|neuroticism
        'is_reverse',   // bool
        'question',     // texto de la pregunta
        'is_active',    // bool
    ];

    protected $casts = [
        'position'   => 'integer',
        'is_reverse' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public const DIMENSIONS = [
        'openness',
        'conscientiousness',
        'extraversion',
        'agreeableness',
        'neuroticism',
    ];

    /* -------- Scopes útiles -------- */
    public function scopeActive($q)                 { return $q->where('is_active', true); }
    public function scopeInstrument($q, string $i)  { return $q->where('instrument', $i); }
    public function scopeDimension($q, string $d)   { return $q->where('dimension', $d); }
    public function scopeOrdered($q)                { return $q->orderBy('position'); }

    /* -------- Helper: invertir Likert (1..5) -------- */
    public static function invertLikert(int $value, int $min = 1, int $max = 5): int
    {
        return ($max + $min) - $value; // 6 - value para escala 1..5
    }
}
