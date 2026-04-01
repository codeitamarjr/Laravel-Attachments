<?php

namespace CodeItamarJr\Attachments\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Attachable
{
    /**
     * Get all attachments associated with the model.
     */
    public function attachments(): MorphMany;

    /**
     * Get the attachment relation for a specific collection.
     */
    public function attachment(string $collection = 'default'): MorphOne;
}
