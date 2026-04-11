<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_id',
        'category',
        'patient',
        'experience',
        'bio_data',
        'status',
        'consultation_fee',
        'available_from',
        'available_to',
        'rating',          // ✅ added
        'rating_count',    // ✅ added
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'doc_id', 'id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'doctor_id', 'id');
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    public function getRatingCountAttribute(): int
    {
        return $this->reviews()->count();
    }
}