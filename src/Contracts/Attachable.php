<?php

namespace CodeItamarJr\Attachments\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Attachable
{
    public function attachments(): MorphMany;

    public function attachment(string $collection = 'default'): MorphOne;
}
