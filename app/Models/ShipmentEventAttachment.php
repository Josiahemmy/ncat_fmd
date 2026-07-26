<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to a shipment event: the airway bill, the customs release
 * note, the agent's invoice.
 *
 * Unlike the event itself, an attachment can be deleted. The event is the
 * record and stays immutable; a file hung off it is evidence that may have
 * been attached to the wrong entry, and removing it does not rewrite what the
 * timeline says happened.
 */
class ShipmentEventAttachment extends Model
{
    use HasFactory;

    /** Accepted types. Anything else is refused at validation. */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * 5 MB per file. A phone photograph of an airway bill lands at roughly 2
     * to 4 MB, so this clears realistic input without inviting someone to park
     * a video on a shared-hosting disk that also holds the database backups.
     */
    public const MAX_SIZE_KB = 5120;

    protected $fillable = [
        'shipment_event_id', 'disk', 'path', 'original_name',
        'mime_type', 'size_bytes', 'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** Remove the file whenever its row goes, so a purge leaves no orphans. */
    protected static function booted(): void
    {
        static::deleted(function (self $attachment): void {
            $attachment->deleteFile();
        });
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ShipmentEvent::class, 'shipment_event_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Short label for the timeline: "PDF", "JPG". */
    public function kind(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPG',
            'image/png' => 'PNG',
            'image/webp' => 'WEBP',
            default => 'FILE',
        };
    }

    /** Human size for the timeline, e.g. "1.4 MB". */
    public function readableSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
