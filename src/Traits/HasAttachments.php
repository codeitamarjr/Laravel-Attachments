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
     * Get the attachment relation for a specific collection.
     */
    public function attachment(string $collection = Attachment::DEFAULT_COLLECTION): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('collection', $collection);
    }

    /**
     * Resolve the URL for the first attachment in the given collection.
     *
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  DateTimeInterface|null  $expiresAt  Expiration time for private URLs.
     */
    public function attachmentUrl(string $collection = Attachment::DEFAULT_COLLECTION, ?DateTimeInterface $expiresAt = null): ?string
    {
        $attachment = $this->relationLoaded('attachments')
            ? $this->attachments->firstWhere('collection', $collection)
            : $this->attachment($collection)->first();

        return $attachment?->url($expiresAt);
    }

    /**
     * Resolve the attachment service from the container.
     */
    protected static function resolveAttachmentService(): AttachmentService
    {
        return app(AttachmentService::class);
    }
}
