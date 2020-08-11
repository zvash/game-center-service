<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = [
        'game_id',
        'level_index',
        'boxes_count',
        'winner_box',
        'revealable_boxes',
        'reveal_price',
        'win_prize',
        'leave_prize',
        'last_move_time',
        'status'
    ];
}
