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

    public const DEFAULT_COLLECTION = 'default';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const DEFAULT_PRIVATE_URL_TTL = 5;

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

    /**
     * Get the owning attachable model relation.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the uploader relation using the configured uploader model and key.
     *
     * @throws RuntimeException
     */
    public function uploader(): BelongsTo
    {
        $model = config('attachments.uploader_model', config('auth.providers.users.model'));
        $foreignKey = config('attachments.uploader_foreign_key', 'uploaded_by');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('Attachment uploader model is not configured.');
        }

        return $this->belongsTo($model, $foreignKey);
    }

    /**
     * Determine whether the attachment is publicly accessible.
     */
    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /**
     * Determine whether the attachment uses private visibility.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * Resolve the attachment URL.
     *
     * Public attachments return a normal disk URL. Private attachments
     * return a temporary URL.
     *
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     * @param  array  $options  Additional driver-specific temporary URL options.
     *
     * @throws RuntimeException
     */
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

    /**
     * Generate a temporary URL for a private attachment.
     *
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for the temporary URL.
     * @param  array  $options  Additional driver-specific temporary URL options.
     *
     * @throws RuntimeException
     */
    public function temporaryUrl(?DateTimeInterface $expiresAt = null, array $options = []): ?string
    {
        if (! $this->path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->path,
                $expiresAt ?? now()->addMinutes(config('attachments.private_url_ttl', self::DEFAULT_PRIVATE_URL_TTL)),
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
