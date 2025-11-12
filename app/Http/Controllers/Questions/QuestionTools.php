<?php

namespace App\Http\Controllers\Questions;

use App\Models\Question;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionTools
{
    /**
     * Generate ALL instruments (BFI-44, BFI-36, BFI-24, BFI-12)
     */
    public static function generate_questions(): void
    {
        self::generate_questions_44();
        self::generate_questions_36();
        self::generate_questions_24();
        self::generate_questions_12();
    }

    /**
     * BFI-44 (full)
     */
    public static function generate_questions_44(): void
    {
        $now = Carbon::now();

        // Reverse-keyed (R) oficiales:
        // E: 6R,21R,31R | A: 2R,12R,27R,37R | C: 8R,18R,23R,43R | N: 9R,24R,34R | O: 35R,41R

        $items = [
            // ===== Extraversion (8) =====
            ['position'=>1,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is talkative.'],
            ['position'=>6,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Is reserved.'],
            ['position'=>11, 'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is full of energy.'],
            ['position'=>16, 'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Generates a lot of enthusiasm.'],
            ['position'=>21, 'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Tends to be quiet.'],
            ['position'=>26, 'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Has an assertive personality.'],
            ['position'=>31, 'dimension'=>'extraversion','is_reverse'=>true,  'question'=>'Is sometimes shy, inhibited.'],
            ['position'=>36, 'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is outgoing, sociable.'],

            // ===== Agreeableness (9) =====
            ['position'=>2,  'dimension'=>'agreeableness','is_reverse'=>true, 'question'=>'Tends to find fault with others.'],
            ['position'=>7,  'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is helpful and unselfish with others.'],
            ['position'=>12, 'dimension'=>'agreeableness','is_reverse'=>true,  'question'=>'Starts quarrels with others.'],
            ['position'=>17, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Has a forgiving nature.'],
            ['position'=>22, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is generally trusting.'],
            ['position'=>27, 'dimension'=>'agreeableness','is_reverse'=>true,  'question'=>'Can be cold and aloof.'],
            ['position'=>32, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is considerate and kind to almost everyone.'],
            ['position'=>37, 'dimension'=>'agreeableness','is_reverse'=>true,  'question'=>'Is sometimes rude to others.'],
            ['position'=>42, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Likes to cooperate with others.'],

            // ===== Conscientiousness (9) =====
            ['position'=>3,  'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does a thorough job.'],
            ['position'=>8,  'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Can be somewhat careless.'],
            ['position'=>13, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Is a reliable worker.'],
            ['position'=>18, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be disorganized.'],
            ['position'=>23, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be lazy.'],
            ['position'=>28, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Perseveres until the task is finished.'],
            ['position'=>33, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does things efficiently.'],
            ['position'=>38, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Makes plans and follows through with them.'],
            ['position'=>43, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Is easily distracted.'],

            // ===== Neuroticism (8) =====
            ['position'=>4,  'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Is depressed, blue.'],
            ['position'=>9,  'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is relaxed, handles stress well.'],
            ['position'=>14, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be tense.'],
            ['position'=>19, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Worries a lot.'],
            ['position'=>24, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is emotionally stable, not easily upset.'],
            ['position'=>29, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be moody.'],
            ['position'=>34, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Remains calm in tense situations.'],
            ['position'=>39, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Gets nervous easily.'],

            // ===== Openness (10) =====
            ['position'=>5,  'dimension'=>'openness','is_reverse'=>false,'question'=>'Is original, comes up with new ideas.'],
            ['position'=>10, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is curious about many different things.'],
            ['position'=>15, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is ingenious, a deep thinker.'],
            ['position'=>20, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Has an active imagination.'],
            ['position'=>25, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is inventive.'],
            ['position'=>30, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Values artistic, aesthetic experiences.'],
            ['position'=>35, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Prefers work that is routine.'],
            ['position'=>40, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Likes to reflect, play with ideas.'],
            ['position'=>41, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Has few artistic interests.'],
            ['position'=>44, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is sophisticated in art, music, or literature.'],
        ];

        foreach ($items as $i) {
            Question::updateOrCreate(
                ['instrument' => 'BFI-44', 'position' => $i['position']],
                [
                    'dimension'  => $i['dimension'],
                    'is_reverse' => $i['is_reverse'],
                    'question'   => $i['question'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * BFI-36
     * Selección balanceada desde BFI-44, re-enumerada 1..36
     */
    public static function generate_questions_36(): void
    {
        $now = Carbon::now();

        // Reverse-keyed (R) incluidos según BFI-44
        $items = [
            // ===== Extraversion (7) =====
            ['position'=>1,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is talkative.'],
            ['position'=>2,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Is reserved.'],
            ['position'=>3,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is full of energy.'],
            ['position'=>4,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Generates a lot of enthusiasm.'],
            ['position'=>5,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Tends to be quiet.'],
            ['position'=>6,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Is sometimes shy, inhibited.'],
            ['position'=>7,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is outgoing, sociable.'],

            // ===== Agreeableness (7) =====
            ['position'=>8,  'dimension'=>'agreeableness','is_reverse'=>true, 'question'=>'Tends to find fault with others.'],
            ['position'=>9,  'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is helpful and unselfish with others.'],
            ['position'=>10, 'dimension'=>'agreeableness','is_reverse'=>true,  'question'=>'Starts quarrels with others.'],
            ['position'=>11, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Has a forgiving nature.'],
            ['position'=>12, 'dimension'=>'agreeableness','is_reverse'=>true,  'question'=>'Can be cold and aloof.'],
            ['position'=>13, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is considerate and kind to almost everyone.'],
            ['position'=>14, 'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Likes to cooperate with others.'],

            // ===== Conscientiousness (8) =====
            ['position'=>15, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does a thorough job.'],
            ['position'=>16, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Can be somewhat careless.'],
            ['position'=>17, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Is a reliable worker.'],
            ['position'=>18, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be disorganized.'],
            ['position'=>19, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be lazy.'],
            ['position'=>20, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Perseveres until the task is finished.'],
            ['position'=>21, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does things efficiently.'],
            ['position'=>22, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Makes plans and follows through with them.'],

            // ===== Neuroticism (7) =====
            ['position'=>23, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Is depressed, blue.'],
            ['position'=>24, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is relaxed, handles stress well.'],
            ['position'=>25, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be tense.'],
            ['position'=>26, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Worries a lot.'],
            ['position'=>27, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is emotionally stable, not easily upset.'],
            ['position'=>28, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be moody.'],
            ['position'=>29, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Gets nervous easily.'],

            // ===== Openness (7) =====
            ['position'=>30, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is original, comes up with new ideas.'],
            ['position'=>31, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is curious about many different things.'],
            ['position'=>32, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is ingenious, a deep thinker.'],
            ['position'=>33, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Values artistic, aesthetic experiences.'],
            ['position'=>34, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Prefers work that is routine.'],
            ['position'=>35, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Likes to reflect, play with ideas.'],
            ['position'=>36, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Has few artistic interests.'],
        ];

        foreach ($items as $i) {
            Question::updateOrCreate(
                ['instrument' => 'BFI-36', 'position' => $i['position']],
                [
                    'dimension'  => $i['dimension'],
                    'is_reverse' => $i['is_reverse'],
                    'question'   => $i['question'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * BFI-24
     * Selección balanceada desde BFI-44, re-enumerada 1..24
     */
    public static function generate_questions_24(): void
    {
        $now = Carbon::now();

        // Reverse-keyed (R) incluidos según BFI-44
        $items = [
            // ===== Extraversion (5) =====
            ['position'=>1,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is talkative.'],
            ['position'=>2,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is full of energy.'],
            ['position'=>3,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Tends to be quiet.'],
            ['position'=>4,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is outgoing, sociable.'],
            ['position'=>5,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Has an assertive personality.'],

            // ===== Agreeableness (4) =====
            ['position'=>6,  'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is helpful and unselfish with others.'],
            ['position'=>7,  'dimension'=>'agreeableness','is_reverse'=>true, 'question'=>'Starts quarrels with others.'],
            ['position'=>8,  'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is generally trusting.'],
            ['position'=>9,  'dimension'=>'agreeableness','is_reverse'=>true, 'question'=>'Is sometimes rude to others.'],

            // ===== Conscientiousness (5) =====
            ['position'=>10, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does a thorough job.'],
            ['position'=>11, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Can be somewhat careless.'],
            ['position'=>12, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Perseveres until the task is finished.'],
            ['position'=>13, 'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does things efficiently.'],
            ['position'=>14, 'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be disorganized.'],

            // ===== Neuroticism (5) =====
            ['position'=>15, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Is depressed, blue.'],
            ['position'=>16, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is relaxed, handles stress well.'],
            ['position'=>17, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be moody.'],
            ['position'=>18, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Gets nervous easily.'],
            ['position'=>19, 'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is emotionally stable, not easily upset.'],

            // ===== Openness (5) =====
            ['position'=>20, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is original, comes up with new ideas.'],
            ['position'=>21, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is curious about many different things.'],
            ['position'=>22, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Prefers work that is routine.'],
            ['position'=>23, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Likes to reflect, play with ideas.'],
            ['position'=>24, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is sophisticated in art, music, or literature.'],
        ];

        foreach ($items as $i) {
            Question::updateOrCreate(
                ['instrument' => 'BFI-24', 'position' => $i['position']],
                [
                    'dimension'  => $i['dimension'],
                    'is_reverse' => $i['is_reverse'],
                    'question'   => $i['question'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * BFI-12
     * Selección mínima y balanceada, re-enumerada 1..12
     */
    public static function generate_questions_12(): void
    {
        $now = Carbon::now();

        // Reverse-keyed (R) incluidos según BFI-44
        $items = [
            // ===== Extraversion (2) =====
            ['position'=>1,  'dimension'=>'extraversion','is_reverse'=>false,'question'=>'Is talkative.'],
            ['position'=>2,  'dimension'=>'extraversion','is_reverse'=>true, 'question'=>'Tends to be quiet.'],

            // ===== Agreeableness (2) =====
            ['position'=>3,  'dimension'=>'agreeableness','is_reverse'=>false,'question'=>'Is helpful and unselfish with others.'],
            ['position'=>4,  'dimension'=>'agreeableness','is_reverse'=>true, 'question'=>'Starts quarrels with others.'],

            // ===== Conscientiousness (3) =====
            ['position'=>5,  'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does a thorough job.'],
            ['position'=>6,  'dimension'=>'conscientiousness','is_reverse'=>true, 'question'=>'Tends to be disorganized.'],
            ['position'=>7,  'dimension'=>'conscientiousness','is_reverse'=>false,'question'=>'Does things efficiently.'],

            // ===== Neuroticism (3) =====
            ['position'=>8,  'dimension'=>'neuroticism','is_reverse'=>true, 'question'=>'Is relaxed, handles stress well.'],
            ['position'=>9,  'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Can be moody.'],
            ['position'=>10, 'dimension'=>'neuroticism','is_reverse'=>false,'question'=>'Gets nervous easily.'],

            // ===== Openness (2) =====
            ['position'=>11, 'dimension'=>'openness','is_reverse'=>false,'question'=>'Is curious about many different things.'],
            ['position'=>12, 'dimension'=>'openness','is_reverse'=>true, 'question'=>'Prefers work that is routine.'],
        ];

        foreach ($items as $i) {
            Question::updateOrCreate(
                ['instrument' => 'BFI-12', 'position' => $i['position']],
                [
                    'dimension'  => $i['dimension'],
                    'is_reverse' => $i['is_reverse'],
                    'question'   => $i['question'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
