<?php

namespace App\Jobs;

use App\Events\EmailsStored;
use App\Models\Email;
use App\Services\GmailService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    protected $messages;
    protected $bank;

    public function __construct($messages, $bank)
    {
        $this->messages = $messages;
        $this->bank = $bank;
    }

    public function handle()
    {
        Log::info('Job is being processed...');
        $bulkInsertData = [];
        foreach ($this->messages as $message) {
            $emailData = app(GmailService::class)->getMessageDetails($message->getId(), $this->bank);

            $receivedAt = Carbon::createFromTimestampMs($emailData['date']);
            // Chuyển đổi sang UTC+7
            $receivedAt->setTimezone('Asia/Bangkok');
            if($this->bank == 'HSBC') {
                $emailData['content']['date_success'] = $receivedAt->toDateTimeString();
            }

            $bulkInsertData[] = [
                'gmail_id' => $message->getId(),
                'subject' => $emailData['subject'],
                'from_email' => $emailData['from'],
                'body' => $emailData['snippet'],
                'received_at' => $receivedAt->toDateTimeString(),
                'bank' => $this->bank,
                'account_receiver' => $emailData['content']['account_receiver'],
                'name_receiver' => $emailData['content']['name_receiver'],
                'price' => $emailData['content']['price'],
                'date_success' => $emailData['content']['date_success'],
                'type' => $emailData['content']['type'],
                'content_transfer' => $emailData['content']['content_transfer'],
                'fee_amount' => $emailData['content']['fee_amount'],
                'created_at' => Carbon::now('Asia/Bangkok')->toDateTimeString(), // Lưu theo UTC+7
                'updated_at' => Carbon::now('Asia/Bangkok')->toDateTimeString(), // Lưu theo UTC+7
            ];
        }

        // Thực hiện upsert dữ liệu
        Email::upsert($bulkInsertData, ['gmail_id'], [
            'subject',
            'from_email',
            'body',
            'received_at',
            'bank',
            'account_receiver',
            'name_receiver',
            'price',
            'date_success',
            'type',
            'content_transfer',
            'fee_amount'
        ]);

        // Kích hoạt event sau khi lưu email
        event(new EmailsStored($bulkInsertData, $this->bank));
    }
}
