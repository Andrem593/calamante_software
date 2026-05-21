<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'delivery_date' => 'date',
        'is_invoiced' => 'boolean',
        'is_preinvoiced' => 'boolean',
    ];

    public function getSignatureAttribute($value)
    {
        if (!$value) return null;
        if (strpos($value, 'data:image') === 0) return $value;
        return 'data:image/png;base64,' . $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function trackings()
    {
        return $this->hasMany(OrderTracking::class);
    }
}
