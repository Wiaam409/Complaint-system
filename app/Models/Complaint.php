<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'user_id',
        'department_id',
        'governorate_id',
        'title',
        'description',
        'location',
        'status',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'meta' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attachments()
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function logs()
    {
        return $this->hasMany(ComplaintLog::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }
}
