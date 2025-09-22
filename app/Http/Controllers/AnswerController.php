<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Answer;
use App\Models\Question;

class AnswerController extends Controller
{
    public function store(Request $req)
    {

        // 1) Validate: same array shape as GET (questions[])
        $v = Validator::make(
            $req->all(),
            [
                'assistant_id'            => ['required','uuid'],
                'instrument'              => ['sometimes','string','max:20'],   // default: BFI-44
                'mode'                    => ['sometimes','in:draft,final'],    // default: draft

                'questions'               => ['required','array','min:1'],
                'questions.*.question_id' => ['required','integer','min:1'],

                // Optional (pre-save friendly). If present, must be valid:
                'questions.*.raw'         => ['nullable','integer','between:1,5'],
                'questions.*.scored'      => ['nullable','integer','between:1,5'],
                'questions.*.answered_at' => ['nullable','date'],

                // These can be omitted; backend fills from DB (authoritative):
                'questions.*.position'    => ['nullable','integer','min:1'],
                'questions.*.question'    => ['nullable','string'],
                'questions.*.dimension'   => ['nullable','in:openness,conscientiousness,extraversion,agreeableness,neuroticism'],
                'questions.*.is_reverse'  => ['nullable','boolean'],
            ],
            [
                'assistant_id.required'     => 'assistant_id is required.',
                'assistant_id.uuid'         => 'assistant_id must be a valid UUID.',
                'questions.required'        => 'questions is required.',
                'questions.array'           => 'questions must be an array.',
                'questions.*.question_id.*' => 'Each item must include a valid question_id.',
                'questions.*.raw.between'   => 'raw must be between 1 and 5 when provided.',
                'questions.*.scored.between'=> 'scored must be between 1 and 5 when provided.',
                'mode.in'                   => 'mode must be either "draft" or "final".',
            ]
        );

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'msg'    => $v->errors()->toArray(),
            ], 422);
        }

        $data        = $v->validated();
        $assistantId = $data['assistant_id'];
        $instrument  = $data['instrument'] ?? 'BFI-44';
        $mode        = $data['mode'] ?? 'draft';
        $incoming    = $data['questions'];

        // 2) Ensure row exists and know items_expected
        $itemsExpected = Question::query()
            ->where('instrument', $instrument)
            ->where('is_active', true)
            ->count();

        $answer = Answer::firstOrNew([
            'assistant_id' => $assistantId,
            'instrument'   => $instrument,
        ]);

        if (!$answer->exists) {
            $answer->answers        = [];
            $answer->items_expected = $itemsExpected ?: null;
        }

        // 3) Current saved state (keyed by question_id)
        $current = [];
        foreach ((array) $answer->answers as $it) {
            if (is_array($it) && isset($it['question_id'])) {
                $current[(int)$it['question_id']] = $it;
            }
        }

        // 4) Load authoritative metadata for all referenced question_ids
        $qids = collect($incoming)->pluck('question_id')->filter()->unique()->values()->all();

        $meta = Question::query()
            ->whereIn('id', $qids)
            ->where('instrument', $instrument)
            ->where('is_active', true)
            ->get(['id','position','dimension','is_reverse','question'])
            ->keyBy('id');

        $unknown = array_values(array_diff($qids, $meta->keys()->all()));
        if (!empty($unknown)) {
            return response()->json([
                'status' => false,
                'msg'    => ['questions' => ['Unknown question_id(s) for this instrument: '.implode(',', $unknown)]],
            ], 422);
        }

        // 5) Merge: DB metadata (authoritative) + incoming fields (raw/scored/answered_at)
        foreach ($incoming as $it) {
            if (!is_array($it) || !isset($it['question_id'])) continue;
            $qid = (int) $it['question_id'];
            $m   = $meta[$qid];

            // Start from DB metadata
            $row = $current[$qid] ?? [];
            $row['question_id'] = $qid;
            $row['position']    = (int) $m->position;
            $row['question']    = (string) $m->question;
            $row['dimension']   = (string) $m->dimension;
            $row['is_reverse']  = (bool)  $m->is_reverse;

            // Overlay user-input fields
            if (array_key_exists('raw', $it)) {
                $row['raw'] = is_null($it['raw']) ? null : (int)$it['raw'];
            }
            if (array_key_exists('scored', $it)) {
                $row['scored'] = is_null($it['scored']) ? null : (int)$it['scored'];
            }
            if (!empty($it['answered_at'])) {
                $row['answered_at'] = $it['answered_at'];
            } elseif (isset($row['raw']) && $row['raw'] !== null && empty($row['answered_at'])) {
                $row['answered_at'] = now()->toIso8601String();
            }

            // Compute scored if missing but raw present (Likert 1..5)
            if (isset($row['raw']) && $row['raw'] !== null && !isset($row['scored'])) {
                $row['scored'] = $row['is_reverse'] ? (6 - (int)$row['raw']) : (int)$row['raw'];
            }

            // If raw is null, clear scored
            if (!isset($row['raw']) || $row['raw'] === null) {
                $row['scored'] = null;
            }

            $current[$qid] = $row;
        }

        // 6) Persist merged array (sorted by position)
        $merged = collect($current)
            ->sortBy(fn($it) => $it['position'] ?? PHP_INT_MAX)
            ->values()
            ->all();

        $answer->answers        = $merged; // stored as JSON
        $answer->items_expected = $answer->items_expected ?: ($itemsExpected ?: null);

        // 7) Recompute averages & optionally mark completion
        $answer->recomputeAverages(false);

        if ($mode === 'final' && $answer->items_expected && $answer->items_answered >= $answer->items_expected) {
            $answer->completed_at = $answer->completed_at ?? now();
        }

        $answer->save();

        return response()->json([
            'status' => true,
            'data'   => $answer->fresh(),
        ]);
    }
}
