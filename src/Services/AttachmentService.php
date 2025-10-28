<?php

namespace CodeItamarJr\Attachments\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use CodeItamarJr\Attachments\Models\Attachment;

class AttachmentService
{
    public function store(Model $attachable, UploadedFile $file, string $collection = 'default', ?int $uploadedBy = null): Attachment
    {
        $disk = config('attachments.disk');
        $directory = trim(config('attachments.directory'), '/');
        $prefix = $this->buildPathPrefix($attachable, $collection, $directory);
        $filename = $file->hashName();

        $path = $file->storePubliclyAs($prefix, $filename, $disk);

        return $attachable->attachments()->create([
            'collection' => $collection,
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function replace(Model $attachable, UploadedFile $file, string $collection = 'default', ?int $uploadedBy = null): Attachment
    {
        $this->delete($attachable, $collection);

        return $this->store($attachable, $file, $collection, $uploadedBy);
    }

    public function delete(Model $attachable, ?string $collection = 'default'): void
    {
        $query = $attachable->attachments();

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        $query->each(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
    }

    protected function buildPathPrefix(Model $attachable, string $collection, string $directory): string
    {
        $model = Str::kebab(class_basename($attachable));

        return trim("{$directory}/{$model}/{$attachable->getKey()}/{$collection}", '/');
    }
}
