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
    /**
     * Store a file for an attachable model and persist its metadata.
     *
     * @param  Model  $attachable  The Eloquent model that owns the attachment.
     * @param  UploadedFile  $file  The uploaded file instance to store.
     * @param  string  $collection  Logical collection name for the attachment.
     * @param  int|null  $uploadedBy  Identifier of the uploader model, if available.
     * @param  string|null  $visibility  Override the default visibility with "public" or "private".
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function store(
        Model $attachable,
        UploadedFile $file,
        string $collection = Attachment::DEFAULT_COLLECTION,
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

    /**
     * Replace all attachments in a collection with a new file.
     *
     * @param  Model  $attachable  The Eloquent model that owns the attachment.
     * @param  UploadedFile  $file  The uploaded file instance to store.
     * @param  string  $collection  Logical collection name whose existing attachments should be replaced.
     * @param  int|null  $uploadedBy  Identifier of the uploader model, if available.
     * @param  string|null  $visibility  Override the default visibility with "public" or "private".
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function replace(
        Model $attachable,
        UploadedFile $file,
        string $collection = Attachment::DEFAULT_COLLECTION,
        ?int $uploadedBy = null,
        ?string $visibility = null
    ): Attachment
    {
        $this->guardAttachable($attachable);
        $this->delete($attachable, $collection);

        return $this->store($attachable, $file, $collection, $uploadedBy, $visibility);
    }

    /**
     * Replace a single attachment in place without clearing the rest of the collection.
     *
     * @param  Model  $attachable  The Eloquent model that owns the attachment.
     * @param  int  $attachmentId  Identifier of the attachment record to replace.
     * @param  UploadedFile  $file  The uploaded file instance to store.
     * @param  int|null  $uploadedBy  Identifier of the uploader model, if available.
     * @param  string|null  $visibility  Override the default visibility with "public" or "private".
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function replaceById(
        Model $attachable,
        int $attachmentId,
        UploadedFile $file,
        ?int $uploadedBy = null,
        ?string $visibility = null
    ): Attachment {
        $this->guardAttachable($attachable);

        /** @var Attachment $attachment */
        $attachment = $attachable->attachments()->findOrFail($attachmentId);
        $collection = $attachment->collection;

        $this->deleteAttachmentRecord($attachment);

        return $this->store($attachable, $file, $collection, $uploadedBy, $visibility);
    }

    /**
     * Delete one collection or all attachments for the given model.
     *
     * @param  Model  $attachable  The Eloquent model that owns the attachments.
     * @param  string|null  $collection  Collection name to delete, or null for all collections.
     *
     * @throws InvalidArgumentException
     */
    public function delete(Model $attachable, ?string $collection = Attachment::DEFAULT_COLLECTION): void
    {
        $this->guardAttachable($attachable);
        $query = $attachable->attachments();

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        $query->each(fn (Attachment $attachment) => $this->deleteAttachmentRecord($attachment));
    }

    /**
     * Build the directory prefix used to store a file for an attachable model.
     */
    protected function buildPathPrefix(Model $attachable, string $collection, string $directory): string
    {
        $model = Str::kebab(class_basename($attachable));

        return trim("{$directory}/{$model}/{$attachable->getKey()}/{$collection}", '/');
    }

    /**
     * Resolve and validate the requested attachment visibility.
     *
     * @throws InvalidArgumentException
     */
    protected function resolveVisibility(?string $visibility): string
    {
        $visibility = strtolower($visibility ?? config('attachments.visibility', Attachment::VISIBILITY_PUBLIC));

        if (! in_array($visibility, [Attachment::VISIBILITY_PUBLIC, Attachment::VISIBILITY_PRIVATE], true)) {
            throw new InvalidArgumentException('Attachment visibility must be either "public" or "private".');
        }

        return $visibility;
    }

    /**
     * Ensure the provided model supports the package attachment contract.
     *
     * @throws InvalidArgumentException
     */
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

    /**
     * Delete the stored file and database record for a single attachment.
     */
    protected function deleteAttachmentRecord(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
