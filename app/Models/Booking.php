<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
class Booking extends Model
{
    use HasUlids;use HasFactory;

    protected $fillable = [
    'customer_name',
    'customer_email',
    'vehicle_reg',
    'from_date',
    'to_datetime',
    'status',
    'total_minor',
    'currency',
    'request_fingerprint',
    ];

    protected $casts = [
        'from_date'   => 'immutable_date',
        'to_datetime' => 'immutable_datetime',
        'status'      => BookingStatus::class,
        'total_minor' => 'integer',

    ];

        protected $hidden = [
        'request_fingerprint',
        'vehicle_reg_normalized',
    ];

        protected static function booted(): void
        {
            static::saving(function (self $b) {
            if ($b->vehicle_reg !== null) {
                $raw = preg_replace('/\s+/', '', $b->vehicle_reg);
                $b->vehicle_reg_normalized = strtoupper($raw);
            }
        });

            static::creating(function (self $b) {
                $prefix = config('booking.reference_prefix', 'BK-');
                $b->reference ??= $prefix . str()->upper(str()->ulid());
                $b->status ??= BookingStatus::Active;
            });
        }

    public function days()
    {
        return $this->hasMany(BookingDay::class);
    }

    protected function customerEmail(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value !== null ? strtolower(trim($value)) : null,
        );
    }

        protected function vehicleReg(): Attribute
        {
            return Attribute::make(
                set: function ($value) {
                    if ($value === null) return null;
                    $value = trim($value);
                    return preg_replace('/\s+/', ' ', strtoupper($value)); // purely display normalization
                },
            );
        }

    // `$booking->total` returns a Brick\Money\Money (property access, not a method)
    protected function total(): Attribute
    {
        return Attribute::get(fn () => Money::ofMinor($this->total_minor, $this->currency));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', BookingStatus::Active);
    }

    public function scopeForEmail(Builder $q, string $email): Builder
    {
        return $q->where('customer_email', strtolower(trim($email)));
    }

     /**
     * Filter by a vehicle registration, to missing normalized column.
     */
    public function scopeForReg(Builder $q, string $rawReg): Builder
    {
        $norm = Str::upper(preg_replace('/\s+/', '', $rawReg));
        return $q->where('vehicle_reg_normalized', $norm);
    }
}
