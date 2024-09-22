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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $bank = $request->input('bank') ?? 'BIDV';

        $bank = strtoupper($bank);

        switch ($bank) {
            case 'BIDV':
                $fromEmail = 'bidvsmartbanking@bidv.com.vn';
                break;
            case 'VPBANK':
                $fromEmail = 'vpbankonline@vpb.com.vn';
                break;
            case 'HSBC':
                $fromEmail = 'HSBC@notification.hsbc.com.hk';
                break;
            default:
                $fromEmail = '';
                break;
        }
        if (!$fromEmail) {
            return response()->json(['message' => 'Invalid bank!']);
        }
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
                'bank' => $bank, // Giả sử 'snippet' chứa nội dung email
                'account_receiver' => $emailData['content']['account_receiver'], // Giả sử 'snippet' chứa nội dung email
                'name_receiver' => $emailData['content']['name_receiver'], // Giả sử 'snippet' chứa nội dung email
                'price' => $emailData['content']['price'], // Giả sử 'snippet' chứa nội dung email
                'date_success' => $emailData['content']['date_success'], // Giả sử 'snippet' chứa nội dung email
                'body' => $emailData['snippet'],
                'type' => $emailData['content']['type'], // Giả sử 'snippet' chứa nội dung email
                'content_transfer' => $emailData['content']['content_transfer'], // Giả sử 'snippet' chứa nội dung email
                'fee_amount' => $emailData['content']['fee_amount'], // Giả sử 'snippet' chứa nội dung email
                'received_at' => date('Y-m-d H:i:s', $emailData['date']/1000),
            ];

            // $mail[] = $emailData['content'];

            // Lưu email vào cơ sở dữ liệu
            Email::updateOrCreate(
                ['gmail_id' => $message->getId()],
                [
                    'subject' => $emailData['subject'],
                    'from_email' => $emailData['from'],
                    'body' => $emailData['snippet'],
                    'received_at' => date('Y-m-d H:i:s', $emailData['date']/1000),
                    'bank' => $bank,
                    'account_receiver' => $emailData['content']['account_receiver'],
                    'name_receiver' => $emailData['content']['name_receiver'],
                    'price' => $emailData['content']['price'],
                    'date_success' => $emailData['content']['date_success'],
                    'type' => $emailData['content']['type'],
                    'content_transfer' => $emailData['content']['content_transfer'],
                    'fee_amount' => $emailData['content']['fee_amount'],

                ]
            );
        }

        return response()->json(['message' => 'Emails stored successfully!', 'data' => $mail]);
    }

    public function getEmails(Request $request)
    {

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $bank = $request->input('bank') ?? 'BIDV';

        $emails = Email::where('bank', $bank)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('date_success', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                return $query->where('date_success', '<=', $endDate);
            })
            ->get();

        return response()->json(['data' => $emails]);
    }
}
