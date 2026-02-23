<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['order_no', 'style', 'buyer', 'qty_order', 'due_date'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
