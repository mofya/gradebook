<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = ['name', 'weight', 'course_id'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
