<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Encryptable;

class Booking extends Model
{
    use HasFactory, Encryptable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bookable_type',
        'bookable_id',
        'check_in_date',
        'check_out_date',
        'total_price',
        'status',
        'guests_count',
        'special_requests',
        'payment_method',
        'payment_status',
    ];

    /**
     * The attributes that should be encrypted.
     *
     * @var array
     */
    protected $encryptable = [
        'special_requests',
        'payment_method',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_price' => 'decimal:2',
        'guests_count' => 'integer',
    ];

    /**
     * Get the user that owns the booking
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bookable entity (hotel or yacht)
     */
    public function bookable()
    {
        return $this->morphTo();
    }

    /**
     * Get booking reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('check_in_date', [$startDate, $endDate]);
    }

    /**
     * Scope for pending bookings
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for confirmed bookings
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope for cancelled bookings
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Get booking duration in days
     */
    public function getDurationAttribute()
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    /**
     * Check if booking can be cancelled
     */
    public function canBeCancelled()
    {
        return $this->status === 'confirmed' && 
               $this->check_in_date->isAfter(now()->addDays(2));
    }

    /**
     * Cancel booking
     */
    public function cancel()
    {
        if ($this->canBeCancelled()) {
            $this->update(['status' => 'cancelled']);
            return true;
        }
        return false;
    }

    /**
     * Confirm booking
     */
    public function confirm()
    {
        $this->update(['status' => 'confirmed']);
    }

    /**
     * Get booking summary
     */
    public function getSummaryAttribute()
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->bookable_type),
            'name' => $this->bookable->name,
            'dates' => $this->check_in_date->format('M d') . ' - ' . $this->check_out_date->format('M d'),
            'total_price' => $this->total_price,
            'status' => $this->status,
        ];
    }
}