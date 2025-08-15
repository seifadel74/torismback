<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Yacht extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'location',
        'price_per_day',
        'rating',
        'images',
        'amenities',
        'capacity',
        'length',
        'year_built',
        'crew_size',
        'phone',
        'email',
        'website',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'price_per_day' => 'decimal:2',
        'rating' => 'decimal:1',
        'capacity' => 'integer',
        'length' => 'decimal:2',
        'year_built' => 'integer',
        'crew_size' => 'integer',
    ];

    /**
     * Get all bookings for this yacht
     */
    public function bookings()
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Get yacht reviews
     */
    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Scope for filtering by location
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', "%{$location}%");
    }

    /**
     * Scope for filtering by price range
     */
    public function scopeByPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('price_per_day', [$minPrice, $maxPrice]);
    }

    /**
     * Scope for filtering by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    /**
     * Scope for filtering by capacity
     */
    public function scopeByCapacity($query, $capacity)
    {
        return $query->where('capacity', '>=', $capacity);
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? $this->rating;
    }

    /**
     * Check if yacht is available for given dates
     */
    public function isAvailable($checkIn, $checkOut)
    {
        return !$this->bookings()
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * Get yacht size category
     */
    public function getSizeCategoryAttribute()
    {
        if ($this->length < 30) {
            return 'Small';
        } elseif ($this->length < 50) {
            return 'Medium';
        } else {
            return 'Large';
        }
    }
}