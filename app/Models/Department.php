<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name'];

    public function governorates()
    {
        return $this->belongsToMany(Governorate::class, 'department_governorates');
    }
    public function employees()
    {
        return $this->hasMany(User::class);
    }


}
