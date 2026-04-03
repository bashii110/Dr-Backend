<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable=[

        "doc_id",
        "category",
        "experience",
        "patients",
        "bio_data",
        "status",
    ];

    
    //this belongs to usertable
    public function user(){
        return $this->belongsTo(User::class);
    }
}
