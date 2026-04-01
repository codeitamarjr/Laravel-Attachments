<?php

namespace CodeItamarJr\Attachments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class PlainModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
