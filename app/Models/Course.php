<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['name', 'code', 'year_id'];

    public function year()
    {
        return $this->belongsTo(Year::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class)->withTimestamps();
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}
