<?php

namespace CodeItamarJr\Attachments\Services;

use CodeItamarJr\Attachments\Contracts\Attachable as AttachableContract;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use CodeItamarJr\Attachments\Models\Attachment;

class AttachmentService
{
    public function store(
        Model $attachable,
        UploadedFile $file,
        string $collection = 'default',
        ?int $uploadedBy = null,
        ?string $visibility = null
    ): Attachment
    {
        $this->guardAttachable($attachable);

        $disk = config('attachments.disk');
        $directory = trim(config('attachments.directory'), '/');
        $visibility = $this->resolveVisibility($visibility);
        $prefix = $this->buildPathPrefix($attachable, $collection, $directory);
        $filename = $file->hashName();

        $path = $file->storeAs($prefix, $filename, [
            'disk' => $disk,
            'visibility' => $visibility,
        ]);

        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the attachment file.');
        }

        return $attachable->attachments()->create([
            'collection' => $collection,
            'disk' => $disk,
            'path' => $path,
            'visibility' => $visibility,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function replace(
        Model $attachable,
        UploadedFile $file,
        string $collection = 'default',
        ?int $uploadedBy = null,
        ?string $visibility = null
    ): Attachment
    {
        $this->guardAttachable($attachable);
        $this->delete($attachable, $collection);

        return $this->store($attachable, $file, $collection, $uploadedBy, $visibility);
    }

    public function delete(Model $attachable, ?string $collection = 'default'): void
    {
        $this->guardAttachable($attachable);
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

    protected function resolveVisibility(?string $visibility): string
    {
        $visibility = strtolower($visibility ?? config('attachments.visibility', 'public'));

        if (! in_array($visibility, ['public', 'private'], true)) {
            throw new InvalidArgumentException('Attachment visibility must be either "public" or "private".');
        }

        return $visibility;
    }

    protected function guardAttachable(Model $attachable): void
    {
        if (! $attachable instanceof AttachableContract) {
            throw new InvalidArgumentException(sprintf(
                'Model [%s] must implement [%s] to use attachments.',
                $attachable::class,
                AttachableContract::class
            ));
        }
    }
}
