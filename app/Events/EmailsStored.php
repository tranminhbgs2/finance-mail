<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailsStored
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $emails;
    public $bank;

    /**
     * Create a new event instance.
     *
     * @param array $emails
     * @param string $bank
     */
    public function __construct($emails, $bank)
    {
        $this->emails = $emails;
        $this->bank = $bank;
    }
}
