<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
