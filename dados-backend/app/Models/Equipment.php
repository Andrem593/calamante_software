<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipments';

    protected $fillable = [
        'model',
        'capacity',
    ];

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_equipment');
    }
}
