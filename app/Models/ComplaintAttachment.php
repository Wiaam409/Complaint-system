<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'file_path',
        'mime',
        'original_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

   

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }
}
