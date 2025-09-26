<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Media;

class WhatsappConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        // --- Conversación (como la estabas presentando)
        [$joinedText, $msgCount] = $this->parseConversation($this->conversation);

        // --- Texto crudo opcional (?include_text=1)
        $includeText = filter_var($request->query('include_text', false), FILTER_VALIDATE_BOOLEAN);
        $raw = null;
        if ($includeText) {
            $raw = $this->conversation;
            if (!is_string($raw)) {
                $raw = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // --- Paginación de media (?media_page=1&media_per_page=25)
        $mediaPage    = max(1, (int) $request->query('media_page', 1));
        $mediaPerPage = max(1, min(200, (int) $request->query('media_per_page', 25)));

        // Vinculación por assistant_id + metadata->conversation_id (o por whatsapp_media_file_id)
        $mediaQuery = Media::query()
            ->where('assistant_id', $this->assistant_id)
            ->where(function ($q) {
                $q->whereRaw("(metadata->>'conversation_id') = ?", [$this->id])
                  ->orWhereNotNull('whatsapp_media_file_id');
            })
            ->orderByDesc('date_upload');

        $mediaPaginator = $mediaQuery->paginate(
            $mediaPerPage,
            ['*'],
            'media_page',
            $mediaPage
        );

        $mediaItems = collect($mediaPaginator->items())->map(function (Media $m) {
            $mime    = is_array($m->metadata ?? null) ? ($m->metadata['mime'] ?? null) : null;
            $zipPath = $m->whatsapp_media_file_id
                ?: (is_array($m->metadata ?? null) ? ($m->metadata['zip_path'] ?? null) : null);

            return [
                'id'           => (string) $m->id,
                'type'         => (string) $m->type, // 'image'|'audio'|'video'|'text'
                'storage_url'  => $m->storage_url,
                'mime'         => $mime,
                'zip_path'     => $zipPath,
                'date_upload'  => optional($m->date_upload)->toIso8601String(),
            ];
        })->values();

        $out = [
            'id'             => (string) $this->id,
            'assistant_id'   => (string) $this->assistant_id,
            'zip_aws_path'   => $this->zip_aws_path,
            'message_count'  => $msgCount,
            'raw_text_size'  => $this->strlenUtf8($joinedText),
            'preview'        => $this->truncateUtf8($joinedText, 300),
            'metadata'       => $this->metadata ?? [],
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),

            // Media paginado (estilo InteractionCollection)
            'media' => [
                'data' => $mediaItems,
                'pagination' => [
                    'total'        => $mediaPaginator->total(),
                    'count'        => $mediaPaginator->count(),
                    'per_page'     => $mediaPaginator->perPage(),
                    'current_page' => $mediaPaginator->currentPage(),
                    'total_pages'  => $mediaPaginator->lastPage(),
                ],
            ],
        ];

        if ($includeText) {
            $out['text'] = $raw;
            $out['byte_size'] = function_exists('mb_strlen') ? mb_strlen($raw ?? '', '8bit') : strlen($raw ?? '');
        }

        return $out;
    }

    // ==== tus helpers existentes (no se tocan) ====

    protected function parseConversation($conversation): array
    {
        if (is_string($conversation)) {
            $text  = trim($conversation);
            $count = $text === '' ? 0 : substr_count($text, "\n") + 1;
            return [$text, $count];
        }

        $messages = [];
        $this->walk($conversation, function ($node) use (&$messages) {
            if (is_array($node)) {
                if (isset($node['messages']) && is_array($node['messages'])) {
                    foreach ($node['messages'] as $m) $messages[] = $this->messageToLine($m);
                    return;
                }
                if (isset($node['text']) || isset($node['message']) || isset($node['body']) || isset($node['content'])) {
                    $messages[] = $this->messageToLine($node);
                    return;
                }
                if (array_key_exists(0, $node) && is_string($node[0])) {
                    $messages[] = implode(' ', array_map('strval', $node));
                    return;
                }
            }
        });

        if (empty($messages)) {
            $flatStrings = [];
            $this->walk($conversation, function ($node) use (&$flatStrings) {
                if (is_string($node) || is_numeric($node)) $flatStrings[] = (string) $node;
            });
            $joined = trim(implode("\n", $flatStrings));
            $cnt    = $joined === '' ? 0 : substr_count($joined, "\n") + 1;
            return [$joined, $cnt];
        }

        $messages = array_values(array_filter(array_map(fn($s) => trim((string)$s), $messages), fn($s) => $s !== ''));
        $joined   = implode("\n", $messages);
        return [$joined, count($messages)];
    }

    protected function messageToLine($m): string
    {
        if (!is_array($m)) return is_scalar($m) ? (string) $m : '';

        $author = $m['author'] ?? $m['from'] ?? $m['sender'] ?? null;
        $ts     = $m['timestamp'] ?? $m['date'] ?? $m['time'] ?? null;

        $text = '';
        foreach (['text','message','body','content'] as $k) {
            if (isset($m[$k])) {
                $text = is_array($m[$k]) ? $this->collapseRichText($m[$k]) : (string) $m[$k];
                break;
            }
        }

        $parts = [];
        if ($ts)     $parts[] = "[$ts]";
        if ($author) $parts[] = "{$author}:";
        if ($text !== '') $parts[] = $text;

        return trim(implode(' ', $parts));
    }

    protected function collapseRichText(array $nodes): string
    {
        $out = [];
        $this->walk($nodes, function ($n) use (&$out) {
            if (is_array($n)) {
                if (isset($n['text']) && is_string($n['text'])) $out[] = $n['text'];
                elseif (isset($n['link']) && is_string($n['link'])) $out[] = $n['link'];
            } elseif (is_string($n) || is_numeric($n)) {
                $out[] = (string) $n;
            }
        });
        return trim(implode(' ', $out));
    }

    protected function walk($node, callable $cb): void
    {
        $cb($node);
        if (is_array($node)) foreach ($node as $v) $this->walk($v, $cb);
        elseif (is_object($node)) foreach (get_object_vars($node) as $v) $this->walk($v, $cb);
    }

    protected function strlenUtf8(string $s): int
    {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    protected function truncateUtf8(string $s, int $limit): string
    {
        if ($limit <= 0) return '';
        if ($this->strlenUtf8($s) <= $limit) return $s;
        return function_exists('mb_substr')
            ? rtrim(mb_substr($s, 0, $limit, 'UTF-8')) . '…'
            : rtrim(substr($s, 0, $limit)) . '…';
    }
}
