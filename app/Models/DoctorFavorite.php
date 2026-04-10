<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorFavorite extends Model
{
    protected $fillable = ['user_id', 'doctor_id'];
    public function doctor() { return $this->belongsTo(Doctor::class, 'doctor_id', 'doc_id'); }
}

// ─────────────────────────────────────────────
// In a real project, this would be its own file:
// app/Models/Category.php
// ─────────────────────────────────────────────