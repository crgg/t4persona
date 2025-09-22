<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Question;
use App\Models\Answer;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        // 1) Validate (assistant_id required)
        $validator = Validator::make(
            $request->all(),
            [
                'assistant_id' => ['required','uuid'],
                'instrument'   => ['sometimes','string','max:20'],
            ],
            [
                'assistant_id.required' => 'assistant_id is required.',
                'assistant_id.uuid'     => 'assistant_id must be a valid UUID.',
                'instrument.max'        => 'instrument must not exceed 20 characters.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg'    => $validator->errors()->toArray(),
            ], 422);
        }

        $assistantId = $validator->validated()['assistant_id'];
        $instrument  = $validator->validated()['instrument'] ?? 'BFI-44';

        // 2) Active questions for the instrument (ordered)
        $questions = Question::query()
            ->where('is_active', true)
            ->where('instrument', $instrument)
            ->orderBy('position')
            ->get([
                'id as question_id',
                'position',
                'dimension',
                'is_reverse',
                'question',               // <-- bring the actual question text
            ]);

        $itemsExpected = $questions->count();

        // 3) Existing answers for this assistant (may not exist)
        $answersRow = Answer::query()
            ->where('assistant_id', $assistantId)
            ->where('instrument', $instrument)
            ->first();

        // 4) Prefill map (by question_id) if answers exist
        $byQid = [];
        if ($answersRow && is_array($answersRow->answers)) {
            foreach ($answersRow->answers as $item) {
                if (!is_array($item) || !isset($item['question_id'])) continue;
                $byQid[(int)$item['question_id']] = $item;
            }
        }

        // 5) Build payload (include 'question' so the UI knows what to show)
        $payload = $questions->map(function ($q) use ($byQid) {
            $qid  = (int) $q->question_id;
            $prev = $byQid[$qid] ?? null;

            return [
                'question_id' => $qid,
                'position'    => (int) $q->position,
                'dimension'   => (string) $q->dimension,
                'is_reverse'  => (bool)  $q->is_reverse,
                'question'    => (string)$q->question,     // <-- this is the visible prompt
                // 'text'     => (string)$q->question,     // (optional) keep this if your UI already uses "text"
                'raw'         => $prev['raw']         ?? null,
                'scored'      => $prev['scored']      ?? null,
                'answered_at' => $prev['answered_at'] ?? null,
            ];
        })->values();

        // 6) Progress
        $itemsAnswered = $payload->reduce(
            fn ($carry, $it) => $carry + (!is_null($it['raw']) ? 1 : 0), 0
        );

        return response()->json([
            'status'               => true,
            'assistant_id'         => $assistantId,
            'instrument'           => $instrument,
            'version'              => 1,
            'items_expected'       => $itemsExpected,
            'items_answered'       => $itemsAnswered,
            'completed_at'         => optional($answersRow)->completed_at,
            'has_previous_answers' => (bool) $answersRow,
            'scale'                => [
                'min'    => 1,
                'max'    => 5,
                'labels' => [
                    '1' => 'Strongly disagree',
                    '2' => 'Disagree',
                    '3' => 'Neither agree nor disagree',
                    '4' => 'Agree',
                    '5' => 'Strongly agree'
                ],
                'hint'   => 'Select how much you agree with each statement (1-5).'
            ],
            'questions'            => $payload,
        ]);
    }
}
