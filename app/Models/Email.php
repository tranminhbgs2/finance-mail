<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    // Khai báo các trường có thể được gán giá trị
    protected $fillable = [
        'gmail_id',
        'subject',
        'from_email',
        'bank',
        'name_receiver',
        'price',
        'date_success',
        'body',
        'received_at'
    ];
}
