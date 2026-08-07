<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContentTag extends Pivot
{
    protected $table = 'content_tag';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'content_id',
        'tag_id',
    ];
}
