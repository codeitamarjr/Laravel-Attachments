<?php

namespace CodeItamarJr\Attachments\Contracts;

use CodeItamarJr\Attachments\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Attachable
{
    /**
     * Get all attachments associated with the model.
     */
    public function attachments(): MorphMany;

    /**
     * Get all attachments in a specific collection.
     */
    public function attachmentsFor(string $collection = Attachment::DEFAULT_COLLECTION): MorphMany;

    /**
     * Get the single-file attachment relation for a specific collection.
     */
    public function attachment(string $collection = Attachment::DEFAULT_COLLECTION): MorphOne;
}
