<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EuroExchangeRate extends Model
{
    protected $fillable = ['currency', 'rate'];
}
