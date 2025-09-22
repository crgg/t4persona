<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    protected $table = 'answers';

    protected $fillable = [
        'assistant_id',
        'instrument',
        'answers',
        'items_expected',
        'items_answered',
        'completed_at',
        'openness_avg',
        'conscientiousness_avg',
        'extraversion_avg',
        'agreeableness_avg',
        'neuroticism_avg',
    ];

    protected $casts = [
        'answers'                => 'array',
        'items_expected'         => 'integer',
        'items_answered'         => 'integer',
        'completed_at'           => 'datetime',
        'openness_avg'           => 'decimal:3',
        'conscientiousness_avg'  => 'decimal:3',
        'extraversion_avg'       => 'decimal:3',
        'agreeableness_avg'      => 'decimal:3',
        'neuroticism_avg'        => 'decimal:3',
    ];

    /*
        #estructura de answers

        {
        "assistant_id": "b6f1e2e8-2d2a-4d0c-883e-9b0b7d0a9a31",
        "instrument": "BFI-44",
        "items_expected": 44,
        "answers": {

            "101": {
            "position": 1,
            "question": "Is talkative.",
            "dimension": "extraversion",
            "is_reverse": false,
            "raw": 4,                 // marcado por el usuario (1..5)
            "scored": 4,              // opcional; si no viene, el backend lo calcula (reversa = 6 - raw)
            "answered_at": "2025-09-22T15:50:01Z"
            },

            "102": {
            "position": 2,
            "question": "Tends to find fault with others.",
            "dimension": "agreeableness",
            "is_reverse": true,
            "raw": 5,
            "scored": 1,
            "answered_at": "2025-09-22T15:50:06Z"
            }

        }
        }


    */


    public const DIMENSIONS = [
        'openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism'
    ];

    /* ---------- Relaciones ---------- */
    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }

    /* ---------- Scopes útiles ---------- */
    public function scopeForAssistant($q, string $assistantId)
    { return $q->where('assistant_id', $assistantId); }

    public function scopeInstrument($q, string $instrument)
    { return $q->where('instrument', $instrument); }


    public function recomputeAverages(bool $markCompleted = true): self
    {
        $dims = self::DIMENSIONS;
        $sum = array_fill_keys($dims, 0.0);
        $n   = array_fill_keys($dims, 0);
        $answered = 0;

        $items = (array) $this->answers;

        // Si accidentalmente viene en el formato viejo (objeto), convierto a array
        $isAssoc = !empty($items) && array_keys($items) !== range(0, count($items) - 1);
        if ($isAssoc) {
            $converted = [];
            foreach ($items as $qid => $it) {
                if (!is_array($it)) continue;
                $it['question_id'] = is_numeric($qid) ? (int)$qid : $qid;
                $converted[] = $it;
            }
            $items = $converted;
        }

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $dim = $it['dimension'] ?? null;
            if (!in_array($dim, $dims, true)) continue;

            $raw    = array_key_exists('raw', $it) ? (int)$it['raw'] : null;
            $scored = $it['scored'] ?? null;

            if ($scored === null) {
                if ($raw === null) continue;
                $scored = !empty($it['is_reverse']) ? (6 - $raw) : $raw; // 1..5
            }

            $scored = max(1, min(5, (float)$scored)); // clamp
            $sum[$dim] += $scored;
            $n[$dim]   += 1;
            $answered++;
        }

        $this->openness_avg          = $n['openness']          ? round($sum['openness']          / $n['openness'],          3) : null;
        $this->conscientiousness_avg = $n['conscientiousness'] ? round($sum['conscientiousness'] / $n['conscientiousness'], 3) : null;
        $this->extraversion_avg      = $n['extraversion']      ? round($sum['extraversion']      / $n['extraversion'],      3) : null;
        $this->agreeableness_avg     = $n['agreeableness']     ? round($sum['agreeableness']     / $n['agreeableness'],     3) : null;
        $this->neuroticism_avg       = $n['neuroticism']       ? round($sum['neuroticism']       / $n['neuroticism'],       3) : null;

        $this->items_answered = $answered;

        if ($markCompleted && $this->items_expected && $answered >= $this->items_expected) {
            $this->completed_at = $this->completed_at ?? now();
        }

        return $this;
    }
}
