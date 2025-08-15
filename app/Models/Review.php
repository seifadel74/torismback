<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'comment',
        'booking_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the user that wrote the review
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reviewable entity (hotel or yacht)
     */
    public function reviewable()
    {
        return $this->morphTo();
    }

    /**
     * Get the booking associated with this review
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope for filtering by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for filtering by minimum rating
     */
    public function scopeByMinRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    /**
     * Scope for recent reviews
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get review sentiment
     */
    public function getSentimentAttribute()
    {
        if ($this->rating >= 4) {
            return 'positive';
        } elseif ($this->rating >= 3) {
            return 'neutral';
        } else {
            return 'negative';
        }
    }

    /**
     * Check if review is verified (user has booking)
     */
    public function getIsVerifiedAttribute()
    {
        return $this->booking_id !== null;
    }

    /**
     * Get review summary
     */
    public function getSummaryAttribute()
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user->name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at->format('M d, Y'),
            'is_verified' => $this->is_verified,
        ];
    }
}