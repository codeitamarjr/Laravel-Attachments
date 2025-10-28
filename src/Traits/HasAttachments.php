<?php

namespace QuickTapPay\Attachments\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use QuickTapPay\Attachments\Models\Attachment;
use QuickTapPay\Attachments\Services\AttachmentService;

trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            app(AttachmentService::class)->delete($model, null);
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachment(string $collection = 'default'): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('collection', $collection);
    }

    public function attachmentUrl(string $collection = 'default'): ?string
    {
        $attachment = $this->relationLoaded('attachments')
            ? $this->attachments->firstWhere('collection', $collection)
            : $this->attachment($collection)->first();

        return $attachment?->url();
    }
}
