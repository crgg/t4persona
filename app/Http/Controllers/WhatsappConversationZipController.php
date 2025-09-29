<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Media;
use App\Models\Assistant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\WhatsappConversation;

use Illuminate\Support\Facades\Storage;
use App\Http\Resources\WhatsappConversationResource;
use App\Http\Resources\WhatsappConversationCollection;

class WhatsappConversationZipController extends Controller
{

    public const MAX_UPLOAD_BYTES   = 500 * 1024 * 1024; // 500 MB (raw uploaded ZIP)
    public const MAX_FILES          = 2000;              // entries in the archive
    public const MAX_TOTAL_UNCOMP   = 200 * 1024 * 1024; // 200 MB uncompressed sum
    public const MAX_FILE_BYTES     = 25  * 1024 * 1024; // 25 MB per file

    // Deny by extension (final extension)
    public const DENY_EXT = [
        'exe','dll','com','msi','msp','bat','cmd','sh','ps1','vbs','js','jse','mjs',
        'php','phtml','phar','pl','py','rb','cgi','jar','class','war','apk','ipa',
        'dmg','so','dylib','sys','scr','reg','wasm','lnk'
    ];

    // Deny by MIME (prefix/families)
    public const DENY_MIME_PREFIX = [
        'application/x-msdownload',
        'application/x-dosexec',
        'application/x-executable',
        'application/x-sh',
        'application/x-bat',
        'application/x-msi',
        'application/java-archive',
        'application/vnd.android.package-archive',
        'application/x-mach-binary',
        'application/wasm',
        'text/javascript',
        'application/javascript',
        'application/x-php',
    ];

    // Allow lists
    public const ALLOW_SAFE_MIME_PREFIX = ['image/','video/','audio/','text/'];
    public const ALLOW_SAFE_SINGLE      = ['application/pdf','application/json','application/xml','text/csv','text/vtt','text/srt'];
    public const ALLOW_SAFE_EXT         = [
        'jpg','jpeg','png','gif','webp','heic','bmp',
        'mp4','mov','m4v','webm',
        'mp3','wav','ogg','opus','m4a',
        'txt','log','json','csv','srt','vtt','md','pdf','xml','svg'
    ];

    // Junk to ignore
    public const IGNORE_NAMES = ['__MACOSX/', '.DS_Store', 'Thumbs.db'];


    /**
     * Create WhatsApp conversation by uploading a ZIP with _chat.txt + media.
     * - 500 MB upload cap
     * - Blocks executables/scripts
     * - Anti zip-bomb (entries, per-file size, total uncompressed)
     * - Blocks traversal, nested ZIPs, double extensions, SVG with scripts
     * - Returns Resource + meta
     */
    public function store_whatsapp_zip(Request $request): JsonResponse
    {
        // 1) Initial validation (ZIP + 500MB cap via KB rule)
        $v = \Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'file'         => ['required','file','mimes:zip','mimetypes:application/zip,application/x-zip-compressed','max:512000'], // 500 MB in KB
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }
        $data = $v->validated();

        /** @var UploadedFile $file */
        $file    = $request->file('file');
        $tmpPath = $file->getRealPath();

        // 2) Extra guard in bytes (covers edge cases where php.ini limits are bypassed)
        $sizeBytes = $file->getSize() ?? (@filesize($tmpPath) ?: 0);
        if ($sizeBytes <= 0 || $sizeBytes > self::MAX_UPLOAD_BYTES) {
            return response()->json([
                'status' => false,
                'errors' => ['file' => ['ZIP exceeds 500MB limit or size is unreadable']]
            ], 422);
        }

        // 3) Load assistant
        $assistant = Assistant::findOrFail($data['assistant_id']);

        // 4) Open ZIP
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return response()->json(['status'=>false,'errors'=>['file'=>['_zip open failed']]], 422);
        }

        // 5) Find _chat.txt and enforce anti zip-bomb global caps
        $chatIndex  = -1;
        $numFiles   = $zip->numFiles;
        $totalUncmp = 0;

        if ($numFiles > self::MAX_FILES) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['ZIP has too many entries']]], 422);
        }

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $totalUncmp += (int)($stat['size'] ?? 0);
            if ($totalUncmp > self::MAX_TOTAL_UNCOMP) {
                $zip->close();
                return response()->json(['status'=>false,'errors'=>['file'=>['ZIP uncompressed size too large']]], 422);
            }

            $name = strtolower($stat['name'] ?? '');
            if ($name === '_chat.txt' || str_ends_with($name, '/_chat.txt')) {
                $chatIndex = $i;
            }
        }

        if ($chatIndex === -1) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['ZIP must include _chat.txt']]], 422);
        }

        // 6) Read and normalize conversation
        $chat = $zip->getFromIndex($chatIndex);
        if ($chat === false) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['Unable to read _chat.txt']]], 422);
        }
        $enc = mb_detect_encoding($chat, ['UTF-8','UTF-16','UTF-16LE','UTF-16BE','ISO-8859-1','Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') { $chat = mb_convert_encoding($chat, 'UTF-8', $enc); }
        if (substr($chat, 0, 3) === "\xEF\xBB\xBF") { $chat = substr($chat, 3); }

        // 7) Persist conversation (raw ZIP upload is optional; keep disabled/private if used)
        $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $baseDir  = 'assistants/'.$assistant->id.'/whatsapp_conversation';
        $zipKey   = $baseDir.'/'.(string) Str::uuid().'-'.$fileName;

        $metadata = [
            'source'         => 'whatsapp_zip',
            'zip_original'   => $file->getClientOriginalName(),
            'raw_text_bytes' => strlen($chat),
        ];

        $wc = WhatsappConversation::create([
            'assistant_id' => $assistant->id,
            // 'zip_aws_path' => $zipKey, // only if you actually store the raw zip
            'conversation' => $chat,
            'metadata'     => $metadata,
        ]);

        // 8) FIRST PASS — validate all entries; reject on any violation
        $forbidden = [];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

        for ($i = 0; $i < $numFiles; $i++) {
            if ($i === $chatIndex) continue;

            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $name = $stat['name'] ?? '';
            if ($name === '' || str_ends_with($name, '/')) continue; // directory

            $bn = basename($name);
            if ($bn === '' || in_array($bn, self::IGNORE_NAMES, true)) continue;

            foreach (self::IGNORE_NAMES as $ign) {
                if (str_starts_with($name, $ign) || str_contains($name, '/'.$ign)) {
                    continue 2;
                }
            }

            // 8.1 Path traversal / absolute paths (POSIX + Windows)
            if (str_contains($name, '../') || str_contains($name, '..\\') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name)) {
                $forbidden[] = ['entry'=>$name,'reason'=>'path traversal / absolute path'];
                continue;
            }

            // 8.2 Per-file size
            $uncomp = (int)($stat['size'] ?? 0);
            if ($uncomp > self::MAX_FILE_BYTES) {
                $forbidden[] = ['entry'=>$name,'reason'=>'file too large'];
                continue;
            }

            // 8.3 Dangerous final extension
            $ext = strtolower(pathinfo($bn, PATHINFO_EXTENSION));
            if (in_array($ext, self::DENY_EXT, true)) {
                $forbidden[] = ['entry'=>$name,'reason'=>'dangerous extension'];
                continue;
            }

            // 8.4 Suspicious double extension (e.g., photo.jpg.exe)
            if (self::hasSuspiciousDoubleExt($bn)) {
                $forbidden[] = ['entry'=>$name,'reason'=>'suspicious double extension'];
                continue;
            }

            // 8.5 Read bytes (only if needed & within caps)
            $bytes = $zip->getFromIndex($i);
            if ($bytes === false) {
                $forbidden[] = ['entry'=>$name,'reason'=>'unreadable'];
                continue;
            }

            // 8.6 MIME detection + deny by MIME family
            $mime = self::detectMime($bytes, $finfo);
            if (self::isDeniedByMime($mime)) {
                $forbidden[] = ['entry'=>$name,'reason'=>"dangerous mime ($mime)"];
                continue;
            }

            // 8.7 Positive allowlist (family or single or extension)
            $okByMime = false;
            foreach (self::ALLOW_SAFE_MIME_PREFIX as $p) {
                if (str_starts_with($mime, $p)) { $okByMime = true; break; }
            }
            if (!$okByMime && !in_array($mime, self::ALLOW_SAFE_SINGLE, true) && !in_array($ext, self::ALLOW_SAFE_EXT, true)) {
                $forbidden[] = ['entry'=>$name,'reason'=>"unsupported type ($mime)"];
                continue;
            }

            // 8.8 SVG hardening
            if ($ext === 'svg' || $mime === 'image/svg+xml') {
                $snippet = strtolower(substr($bytes, 0, 4096));
                if (str_contains($snippet, '<script') || preg_match('/on[a-z]+\s*=/i', $snippet)) {
                    $forbidden[] = ['entry'=>$name,'reason'=>'svg with script/handlers'];
                    continue;
                }
            }

            // 8.9 No nested ZIPs
            if ($ext === 'zip' || $mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
                $forbidden[] = ['entry'=>$name,'reason'=>'nested zip not allowed'];
                continue;
            }
        }

        if ($finfo) { @finfo_close($finfo); }

        if (!empty($forbidden)) {
            $zip->close();
            return response()->json([
                'status'=>false,
                'errors'=>['file'=>['ZIP contains forbidden entries','details'=>$forbidden]]
            ], 422);
        }

        // 9) SECOND PASS — upload safe media
        $createdMedia = [];
        $mediaBaseDir = 'assistants/'.$assistant->id.'/media/whatsapp/'.$wc->id;

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

        for ($i = 0; $i < $numFiles; $i++) {
            if ($i === $chatIndex) continue;

            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $name = $stat['name'] ?? '';
            if ($name === '' || str_ends_with($name, '/')) continue;

            $bn = basename($name);
            if ($bn === '' || in_array($bn, self::IGNORE_NAMES, true)) continue;

            foreach (self::IGNORE_NAMES as $ign) {
                if (str_starts_with($name, $ign) || str_contains($name, '/'.$ign)) continue 2;
            }

            $bytes = $zip->getFromIndex($i);
            if ($bytes === false) continue;

            $mime = self::detectMime($bytes, $finfo);
            $type = self::mapType($mime, $name);

            $safeBn = preg_replace('/[^\w\.\-]+/u', '_', $bn);
            $s3key  = $mediaBaseDir.'/'.(string) Str::uuid().'-'.$safeBn;

            Storage::disk('s3')->put($s3key, $bytes, [
                'visibility'  => 'public', // switch to 'private' if you proxy downloads
                'ContentType' => $mime ?: 'application/octet-stream',
            ]);

            $publicUrl = Storage::disk('s3')->url($s3key);

            do { $mid = (string) Str::uuid(); } while (Media::whereKey($mid)->exists());

            $media = new Media();
            $media->id                    = $mid;
            $media->assistant_id          = $assistant->id;
            $media->type                  = $type;
            $media->storage_url           = $publicUrl;
            $media->transcription         = null;
            $media->metadata              = [
                'source'       => 'whatsapp_zip_media',
                'zip_path'     => $name,
                'mime'         => $mime,
                'size'         => strlen($bytes),
                'original_zip' => $file->getClientOriginalName(),
            ];
            $media->whatsapp_media_file_id= $wc->id;
            $media->date_upload           = now();
            $media->save();

            $createdMedia[] = [
                'id'           => $media->id,
                'type'         => $media->type,
                'storage_url'  => $media->storage_url,
                'mime'         => $mime,
                'zip_path'     => $name,
                'date_upload'  => optional($media->date_upload)->toIso8601String(),
            ];
        }

        if ($finfo) { @finfo_close($finfo); }
        $zip->close();

        // 10) Return Resource + meta
        return (new WhatsappConversationResource($wc))
            //->additional([
            //    'status' => true,
            //    'meta'   => [
            //        'media_created' => $createdMedia,
            //        'media_count'   => count($createdMedia),
            //        'zip_aws_path'  => $wc->zip_aws_path, // null if you didn't upload the raw zip
            //    ],
            //])
            ->response()
            ->setStatusCode(201);
    }

    // =====================================================================
    // ========== Helpers converted to public static methods ================
    // =====================================================================

    /**
     * Best-effort MIME detection from raw bytes using finfo.
     * Falls back to 'application/octet-stream'.
     */
    public static function detectMime(string $bytes, $finfo = null): string
    {
        if ($finfo) {
            $m = @finfo_buffer($finfo, $bytes);
            if (is_string($m) && $m !== 'application/octet-stream') {
                return strtolower($m);
            }
        }
        return 'application/octet-stream';
    }

    /**
     * Check if MIME matches any dangerous family prefix.
     */
    public static function isDeniedByMime(string $mime): bool
    {
        $mime = strtolower($mime);
        foreach (self::DENY_MIME_PREFIX as $p) {
            if (str_starts_with($mime, $p)) return true;
        }
        return false;
    }

    /**
     * Detect suspicious double-extension like photo.jpg.exe
     * We only consider the final extension dangerous.
     */
    public static function hasSuspiciousDoubleExt(string $name): bool
    {
        $parts = explode('.', strtolower(basename($name)));
        if (count($parts) >= 3) {
            $last = array_pop($parts);
            if (in_array($last, self::DENY_EXT, true)) return true;
        }
        return false;
    }

    /**
     * Map MIME (and fallback to extension) to internal type buckets.
     * Returns one of: 'image' | 'video' | 'audio' | 'text'
     */
    public static function mapType(string $mime, string $name): string
    {
        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'text/'))  return 'text';

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['txt','srt','vtt','csv','log','json','md','xml'])) return 'text';
        if ($ext === 'pdf' || $mime === 'application/pdf') return 'text';
        return 'text';
    }
        // GET /api/whatsapp-conversations
    public function index(Request $request): JsonResponse
    {
        $v = \Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'per_page'     => ['sometimes','integer','min:1','max:200'],
            'page'         => ['sometimes','integer','min:1'],
        ]);

        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $assistantId = $request->input('assistant_id');

        $q = WhatsappConversation::query()
            ->where('assistant_id', $assistantId)
            ->orderByDesc('created_at');

        $perPage = (int) $request->get('per_page', 25);
        $items   = $q->paginate($perPage);

        $collection = new WhatsappConversationCollection($items);
        return response()->json($collection->toArray($request));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $wc = WhatsappConversation::findOrFail($id);

        // URL del ZIP (best-effort)
        $zipUrl = null;
        try {
            if (method_exists(Storage::disk('s3'), 'temporaryUrl')) {
                $zipUrl = Storage::disk('s3')->temporaryUrl($wc->zip_aws_path, now()->addMinutes(30));
            } elseif (method_exists(Storage::disk('s3'), 'url')) {
                $zipUrl = Storage::disk('s3')->url($wc->zip_aws_path);
            }
        } catch (\Throwable $e) {
            $zipUrl = null;
        }

        // Texto COMPLETO sin parsear
        $raw = $wc->conversation;
        if (!is_string($raw)) {
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }


        $data = (new \App\Http\Resources\WhatsappConversationResource($wc))
            ->toArray($request);


        return response()->json([
            'status' => true,
            'data'   => $data,
        ]);
    }



    public function destroy(string $id): JsonResponse
    {
        $wc = WhatsappConversation::findOrFail($id);

        $deletedMediaRows  = 0;
        $deletedMediaFiles = 0;

        // (1) Borrar MEDIA asociados:
        // Regla de enlace (según tu store): whatsapp_media_file_id = conversation_id
        // Además, si existe zip_original en metadata, filtramos por ese ZIP para mayor precisión.
        $zipOriginal = is_array($wc->metadata ?? null) ? ($wc->metadata['zip_original'] ?? null) : null;

        $mediaQuery = Media::query()
            ->where('assistant_id', $wc->assistant_id)
            ->where('whatsapp_media_file_id', (string) $wc->id);

        if ($zipOriginal) {
            // Postgres JSONB: metadata->>'original_zip' = $zipOriginal
            $mediaQuery->whereRaw("(metadata->>'original_zip') = ?", [$zipOriginal]);
        }

        $mediaList = $mediaQuery->get();

        foreach ($mediaList as $m) {
            // Borrar archivo en S3 (best-effort)
            $s3Key = null;

            // 1) Si guardaste la key en metadata:
            if (is_array($m->metadata ?? null) && !empty($m->metadata['s3_key'])) {
                $s3Key = $m->metadata['s3_key'];
            }

            // 2) Fallback: derivar la key desde storage_url
            if (!$s3Key && !empty($m->storage_url)) {
                $s3Key = $this->deriveS3KeyFromUrl($m->storage_url);
            }

            if ($s3Key) {
                try {
                    if (Storage::disk('s3')->delete($s3Key)) {
                        $deletedMediaFiles++;
                    }
                } catch (\Throwable $e) {
                    // ignorar errores de S3
                }
            }

            // Borrar fila de DB
            try {
                $m->delete();
                $deletedMediaRows++;
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        // (2) Intentar borrar el prefijo/carpeta de esta conversación por si quedaron residuos
        // (este es un best-effort; no falla si no existe)
        try {
            $prefix = "assistants/{$wc->assistant_id}/media/whatsapp/{$wc->id}";
            Storage::disk('s3')->deleteDirectory($prefix);
        } catch (\Throwable $e) {
            // ignorar
        }

        // (3) Borrar el ZIP original de S3 (best-effort)
        if (!empty($wc->zip_aws_path)) {
            try {
                Storage::disk('s3')->delete($wc->zip_aws_path);
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        // (4) Borrar la conversación
        $wc->delete();

        return response()->json([
            'status' => true,
            'msg'    => 'Conversation and media deleted',
            'data'   => [
                'deleted_media_rows'  => $deletedMediaRows,
                'deleted_media_files' => $deletedMediaFiles,
            ],
        ]);
    }

    /**
     * Deriva la S3 key desde una URL pública.
     * Funciona con estilos path-style y virtual-hosted-style.
     */
    protected function deriveS3KeyFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = ltrim($path, '/');

        // Si la URL es del tipo https://s3.amazonaws.com/{bucket}/{key}
        // o https://s3.region.amazonaws.com/{bucket}/{key}
        $bucket = env('AWS_BUCKET');
        if ($bucket && str_starts_with($path, $bucket.'/')) {
            return substr($path, strlen($bucket) + 1);
        }

        // Si ya es solo la key:
        return $path !== '' ? $path : null;
    }


}
