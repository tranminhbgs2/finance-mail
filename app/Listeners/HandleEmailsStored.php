<?php

namespace App\Listeners;

use App\Events\EmailsStored;
use Illuminate\Support\Facades\Log;

class HandleEmailsStored
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EmailsStored $event)
    {
        // Xử lý các tác vụ tiếp theo sau khi email được lưu
        Log::info('Emails have been successfully stored.', ['count' => count($event->emails), 'bank' => $event->bank]);
    }
}
