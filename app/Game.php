<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{

    protected $fillable = ['user_id', 'total_levels', 'state', 'is_active'];

    public function revealOne()
    {

    }

    public function leave()
    {

    }

    public function getLevel()
    {

    }

    public function proceed()
    {

    }
}
