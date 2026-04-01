<?php

namespace CodeItamarJr\Attachments\Traits;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use CodeItamarJr\Attachments\Models\Attachment;
use CodeItamarJr\Attachments\Services\AttachmentService;

trait HasAttachments
{
    /**
     * Register the model event hooks for automatic attachment cleanup.
     */
    public static function bootHasAttachments(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            self::resolveAttachmentService()->delete($model, null);
        });
    }

    /**
     * Get all attachments associated with the model.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get all attachments in a specific collection.
     */
    public function attachmentsFor(string $collection = Attachment::DEFAULT_COLLECTION): MorphMany
    {
        return $this->attachments()->where('collection', $collection);
    }

    /**
     * Get the single-file attachment relation for a specific collection.
     *
     * This method is best suited for collections that are treated as
     * single-slot attachments, such as an avatar, logo, or signed copy.
     */
    public function singleAttachment(string $collection = Attachment::DEFAULT_COLLECTION): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('collection', $collection);
    }

    /**
     * Get the backward-compatible single-file attachment relation alias.
     */
    public function attachment(string $collection = Attachment::DEFAULT_COLLECTION): MorphOne
    {
        return $this->singleAttachment($collection);
    }

    /**
     * Get the first attachment model for a specific collection.
     */
    public function firstAttachment(string $collection = Attachment::DEFAULT_COLLECTION): ?Attachment
    {
        return $this->attachmentsFor($collection)->orderBy('id')->first();
    }

    /**
     * Get the last attachment model for a specific collection.
     */
    public function lastAttachment(string $collection = Attachment::DEFAULT_COLLECTION): ?Attachment
    {
        return $this->attachmentsFor($collection)->orderByDesc('id')->first();
    }

    /**
     * Get the Nth attachment model for a specific collection using a 1-based position.
     *
     * @param  int  $position  One-based attachment position within the collection.
     */
    public function attachmentAt(string $collection = Attachment::DEFAULT_COLLECTION, int $position = 1): ?Attachment
    {
        if ($position < 1) {
            throw new \InvalidArgumentException('Attachment position must be greater than or equal to 1.');
        }

        return $this->attachmentsFor($collection)
            ->orderBy('id')
            ->skip($position - 1)
            ->first();
    }

    /**
     * Resolve the URL for the first attachment in the given collection.
     *
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     */
    public function firstAttachmentUrl(string $collection = Attachment::DEFAULT_COLLECTION, ?DateTimeInterface $expiresAt = null): ?string
    {
        return $this->firstAttachment($collection)?->url($expiresAt);
    }

    /**
     * Resolve the URL for the last attachment in the given collection.
     *
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     */
    public function lastAttachmentUrl(string $collection = Attachment::DEFAULT_COLLECTION, ?DateTimeInterface $expiresAt = null): ?string
    {
        return $this->lastAttachment($collection)?->url($expiresAt);
    }

    /**
     * Resolve the URL for the Nth attachment in the given collection using a 1-based position.
     *
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  int  $position  One-based attachment position within the collection.
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     */
    public function attachmentUrlAt(
        string $collection = Attachment::DEFAULT_COLLECTION,
        int $position = 1,
        ?DateTimeInterface $expiresAt = null
    ): ?string {
        return $this->attachmentAt($collection, $position)?->url($expiresAt);
    }

    /**
     * Resolve the URL for the first attachment in the given collection.
     *
     * This method is kept as a backward-compatible alias to firstAttachmentUrl().
     *
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     */
    public function attachmentUrl(string $collection = Attachment::DEFAULT_COLLECTION, ?DateTimeInterface $expiresAt = null): ?string
    {
        return $this->firstAttachmentUrl($collection, $expiresAt);
    }

    /**
     * Resolve the attachment service from the container.
     */
    protected static function resolveAttachmentService(): AttachmentService
    {
        return app(AttachmentService::class);
    }
}
