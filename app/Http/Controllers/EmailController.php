<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Services\GmailService;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    public function fetchAndStoreEmails(Request $request)
    {
        // $fromEmail = 'vpbankonline@vpb.com.vn';
        $fromEmail = 'HSBC@notification.hsbc.com.hk';

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $bank = $request->input('bank') ?? 'HSBC';

        // Tạo chuỗi truy vấn để lọc email
        $query = "from:{$fromEmail}";
        if ($startDate) {
            $query .= " after:{$startDate}";
        }
        if ($endDate) {
            $query .= " before:{$endDate}";
        }

        $messages = $this->gmailService->getGmailMessages($query);

        $mail = [];
        foreach ($messages as $message) { // Sửa đổi để xử lý mảng các đối tượng Message
            $emailData = $this->gmailService->getMessageDetails($message->getId(), $bank);

            $mail[] = [
                'gmail_id' => $message->getId(), // Lấy ID từ đối tượng Message
                'subject' => $emailData['subject'],
                'from_email' => $emailData['from'],
                'content' => $emailData['content'], // Giả sử 'snippet' chứa nội dung email
                'body' => $emailData['body'], // Giả sử 'snippet' chứa nội dung email
                'received_at' => $emailData['date']
            ];

            // Lưu email vào cơ sở dữ liệu
            // Email::updateOrCreate(
            //     ['gmail_id' => $message->getId()],
            //     [
            //         'subject' => $emailData['subject'],
            //         'from_email' => $emailData['from'],
            //         'body' => $emailData['snippet'],
            //         'received_at' => $emailData['date']
            //     ]
            // );
        }

        return response()->json(['message' => 'Emails stored successfully!', 'data' => $mail]);
    }
}
