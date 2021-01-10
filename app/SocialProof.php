<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SocialProof extends Model
{
    protected $fillable = [
        'user_id',
        'play_count',
        'won_amount',
        'currency',
        'comment',
        'visible',
    ];
}
