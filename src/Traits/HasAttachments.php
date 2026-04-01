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
    public static function bootHasAttachments(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            self::resolveAttachmentService()->delete($model, null);
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachment(string $collection = Attachment::DEFAULT_COLLECTION): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('collection', $collection);
    }

    public function attachmentUrl(string $collection = Attachment::DEFAULT_COLLECTION, ?DateTimeInterface $expiresAt = null): ?string
    {
        $attachment = $this->relationLoaded('attachments')
            ? $this->attachments->firstWhere('collection', $collection)
            : $this->attachment($collection)->first();

        return $attachment?->url($expiresAt);
    }

    protected static function resolveAttachmentService(): AttachmentService
    {
        return app(AttachmentService::class);
    }
}
