<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Assistant;
use App\Models\WhatsappConversation;
use ZipArchive;

class WhatsappConversationZipController extends Controller
{
    public function store_whatsapp_zip(Request $request): JsonResponse
    {
        $v = \Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'file'         => ['required','file','mimes:zip','mimetypes:application/zip,application/x-zip-compressed'],
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }
        $data = $v->validated();

        $assistant = Assistant::findOrFail($data['assistant_id']);

        $file    = $request->file('file');
        $tmpPath = $file->getRealPath();

        // --- abrir ZIP y ubicar _chat.txt ---
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return response()->json(['status'=>false,'errors'=>['file'=>['_zip open failed']]], 422);
        }
        $chatIndex = -1;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $ln   = strtolower($name);
            if ($ln === '_chat.txt' || str_ends_with($ln, '/_chat.txt')) { $chatIndex = $i; break; }
        }
        if ($chatIndex === -1) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['ZIP must include _chat.txt']]], 422);
        }
        $chat = $zip->getFromIndex($chatIndex);
        $zip->close();
        if ($chat === false) {
            return response()->json(['status'=>false,'errors'=>['file'=>['Unable to read _chat.txt']]], 422);
        }

        // --- normalizar a UTF-8 para poder guardarlo como JSON ---
        $enc = mb_detect_encoding($chat, ['UTF-8','UTF-16','UTF-16LE','UTF-16BE','ISO-8859-1','Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') { $chat = mb_convert_encoding($chat, 'UTF-8', $enc); }
        if (substr($chat, 0, 3) === "\xEF\xBB\xBF") { $chat = substr($chat, 3); }

        // --- subir ZIP a S3 (como ya hacías) ---
        $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $baseDir  = 'assistants/'.$assistant->id.'/whatsapp_conversation';
        $zipKey   = $baseDir.'/'.(string) Str::uuid().'-'.$fileName;

        Storage::disk('s3')->putFileAs(
            dirname($zipKey), $file, basename($zipKey),
            ['visibility'=>'public', 'ContentType'=>$file->getClientMimeType() ?: $file->getMimeType()]
        );


        $metadata = [
            'source'        => 'whatsapp_zip',
            'zip_original'  => $file->getClientOriginalName(),
            'raw_text_bytes'=> strlen($chat),
        ];

        // --- guardar en tu tabla whatsapp_conversation ---
        $wc = WhatsappConversation::create([
            'assistant_id' => $assistant->id,
            'zip_aws_path' => $zipKey,
            'conversation' => $chat,
            'metadata'     => $metadata,
        ]);

        return response()->json([
            'status' => true,
            'msg'    => 'WhatsApp ZIP saved; _chat.txt stored as raw JSON',
            'data'   => [
                'id'           => $wc->id,
                'assistant_id' => $wc->assistant_id,
                'zip_aws_path' => $wc->zip_aws_path,
            ],
        ], 201);
    }
}
