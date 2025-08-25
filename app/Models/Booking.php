<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Brick\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property-read Money $total
 */
class Booking extends Model
{
    use HasUlids;
    use HasFactory;

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
        'vehicle_reg_normalized',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $b): void {
            $b->reference ??= 'BK-' . str()->upper(str()->ulid());
            $b->status    ??= BookingStatus::Active;
        });
    }

    /* ---------------- Relationships ---------------- */

    public function days(): HasMany
    {
        return $this->hasMany(BookingDay::class);
    }

    /* ---------------- Accessors / Mutators ---------------- */

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
                if ($value === null) {
                    return null;
                }
                $value = trim($value);
                // Display normalization only (uppercase, collapse inner spaces)
                return preg_replace('/\s+/', ' ', strtoupper($value));
            },
        );
    }

    // $booking->total returns a Brick\Money\Money (property access)
    protected function total(): Attribute
    {
        return Attribute::get(fn () => Money::ofMinor($this->total_minor, $this->currency));
    }

    /* ---------------- Query Scopes ---------------- */

    /** Only active bookings. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', BookingStatus::Active);
    }

    /** Filter by normalized (lowercased/trimmed) email. */
    public function scopeForEmail(Builder $q, string $email): Builder
    {
        return $q->where('customer_email', strtolower(trim($email)));
    }

    /** Filter by normalized vehicle registration (UPPER, no spaces). */
    public function scopeForReg(Builder $q, string $rawReg): Builder
    {
        $norm = Str::upper(preg_replace('/\s+/', '', $rawReg) ?? $rawReg);

        return $q->where('vehicle_reg_normalized', $norm);
    }

    /**
     * Date-range overlap: existing.from < new.to  AND  existing.to > new.from
     * Use with immutable Carbon instances (you already cast them).
     */
    public function scopeOverlaps(Builder $q, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $q->where('from_date', '<', $to)
                 ->where('to_datetime', '>', $from);
    }

    /** Convenience: active bookings for a specific vehicle (email ignored). */
    public function scopeActiveForVehicle(Builder $q, string $rawReg): Builder
    {
        return $q->active()->forReg($rawReg);
    }

    /** Convenience: active bookings for a vehicle that overlap a window. */
    public function scopeActiveOverlappingVehicle(Builder $q, string $rawReg, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $q->active()->forReg($rawReg)->overlaps($from, $to);
    }
}
