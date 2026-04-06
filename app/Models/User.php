<?php
// ═══════════════════════════════════════════════════════════════
// User.php
// ═══════════════════════════════════════════════════════════════
namespace App\Models;

use App\Models\Doctor;
use App\Models\UserDetails;
use Appointment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'type', 'phone', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'doc_id');
    }

    public function userDetails()
    {
        return $this->hasOne(UserDetails::class, 'user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }
}