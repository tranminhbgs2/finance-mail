<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    // Chỉ định bảng failed_jobs
    protected $table = 'failed_jobs';

    // Các cột có thể được cập nhật
    protected $fillable = [
        'id', 'connection', 'queue', 'payload', 'exception', 'failed_at'
    ];

    // Tắt tính năng timestamps nếu bảng không có cột created_at và updated_at
    public $timestamps = false;
}
