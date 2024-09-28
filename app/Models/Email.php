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
        'account_receiver',
        'name_receiver',
        'price',
        'fee_amount',
        'date_success',
        'body',
        'type',
        'content_transfer',
        'received_at'
    ];
}
