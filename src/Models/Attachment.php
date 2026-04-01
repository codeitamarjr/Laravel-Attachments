<?php

namespace CodeItamarJr\Attachments\Models;

use DateTimeInterface;
use RuntimeException;
use Throwable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection',
        'disk',
        'path',
        'visibility',
        'filename',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'uploaded_by');
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function url(?DateTimeInterface $expiresAt = null, array $options = []): ?string
    {
        if (! $this->path) {
            return null;
        }

        if ($this->isPrivate()) {
            return $this->temporaryUrl($expiresAt, $options);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function temporaryUrl(?DateTimeInterface $expiresAt = null, array $options = []): ?string
    {
        if (! $this->path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->path,
                $expiresAt ?? now()->addMinutes(config('attachments.private_url_ttl', 5)),
                $options
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Disk [%s] does not support temporary URLs for private attachments.', $this->disk),
                previous: $exception
            );
        }
    }
}
