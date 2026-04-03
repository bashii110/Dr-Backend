<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetails extends Model
{
     use HasFactory;

    protected $fillable=[

        "user_id",
        "bio_data",
        "status",
    ];

    //this belongs to usertable
    public function user(){
        return $this->belongsTo(User::class);
    }
}
