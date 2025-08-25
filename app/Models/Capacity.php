<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Capacity extends Model
{
    use HasFactory;

    /**
     * The primary key is the day itself (YYYY-MM-DD).
     *
     * @var string
     */
    protected $primaryKey = 'day';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'day' => 'immutable_date',
    ];
}
