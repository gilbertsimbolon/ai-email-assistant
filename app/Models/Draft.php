<?php

namespace App\Models;

use App\Enums\DraftStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'type',
        'provider',
        'content',
        'original_content',
        'ai_log_id',
        'version',
        'status',
    ];

    protected $casts = [
        'status' => DraftStatus::class,
        // Not cast to MessageType — a GHL-sourced draft's channel can be
        // anything GHL supports (SMS/FB/IG/etc), not just email/whatsapp
        // (claude.txt section 16), so `type` stays a plain string.
        'content' => 'array',
        'original_content' => 'array',
        'version' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function aiLog()
    {
        return $this->belongsTo(\App\Models\AiCenter\AiLog::class);
    }

    /**
     * Whether the agent changed the draft body from what the AI generated.
     * Drafts with no `original_content` (manual/non-AI drafts) are never
     * considered "edited" — there is nothing AI-authored to compare against.
     */
    public function wasEditedByAgent(): bool
    {
        if (!$this->original_content) {
            return false;
        }

        return trim((string) ($this->original_content['body'] ?? '')) !== trim((string) ($this->content['body'] ?? ''));
    }

    /**
     * Similarity ratio (0-100) between the AI-generated body and the final
     * body, used to bucket drafts into "sent as-is" / "edited a little" /
     * "edited a lot" for AI Accuracy reporting.
     */
    public function editSimilarityPercent(): ?float
    {
        if (!$this->original_content) {
            return null;
        }

        $original = trim((string) ($this->original_content['body'] ?? ''));
        $current = trim((string) ($this->content['body'] ?? ''));

        if ($original === '' && $current === '') {
            return 100.0;
        }

        similar_text($original, $current, $percent);

        return round($percent, 1);
    }
}
