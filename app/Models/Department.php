<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['dept_name', 'dept_code'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'dept_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
