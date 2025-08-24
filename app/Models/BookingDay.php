<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingDay extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['day' => 'immutable_date'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
