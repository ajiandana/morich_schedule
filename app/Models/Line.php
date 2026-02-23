<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Line extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
