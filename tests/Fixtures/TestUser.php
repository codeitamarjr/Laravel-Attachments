<?php

namespace CodeItamarJr\Attachments\Tests\Fixtures;

use CodeItamarJr\Attachments\Contracts\Attachable;
use CodeItamarJr\Attachments\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestUser extends Model implements Attachable
{
    use HasAttachments;
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];
}
