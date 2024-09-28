<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmails;
use App\Models\Email;
use App\Services\GmailService;
use Carbon\Carbon;
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
        $bank = strtoupper($request->input('bank') ?? 'BIDV');

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

        // $em = [];
        // foreach ($messages as $message) {
        //     $emailData = $this->gmailService->getMessageDetails($message->getId(), $bank);


        //     $receivedAt = Carbon::createFromTimestampMs($emailData['date']);
        //     // Chuyển đổi sang UTC+7
        //     $receivedAt->setTimezone('Asia/Bangkok');
        //     // if(!isset($emailData['date'])) {
        //         $em[] = $receivedAt->toDateTimeString();
        //     // }

        // }
        // return response()->json(['bank' => $em]);
        // die;
        // Dispatch Job để xử lý email không đồng bộ
        ProcessEmails::dispatch($messages, $bank);

        return response()->json(['count' => count($messages), 'message' => 'Emails stored successfully!', 'bank' => $bank]);
    }

    public function getEmails(Request $request)
    {

        $startDate = $request->input('start_date') ?? Carbon::now()->subDays(7)->toDateString() . ' 00:00:00';
        $endDate = $request->input('end_date') ?? Carbon::now()->toDateString() . ' 23:59:59';
        $bank = $request->input('bank') ?? null;

        $emails = Email::when($startDate, function ($query) use ($startDate) {
                return $query->where('date_success', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                return $query->where('date_success', '<=', $endDate);
            })
            ->when($bank, function ($query) use ($bank) {
                return $query->where('bank', $bank);
            })
            ->get() // Lấy kết quả
            ->toArray(); // Chuyển đổi kết quả thành mảng

        return response()->json(['count' => count($emails), 'data' => $emails, 'start_date' => $startDate, 'end_date' => $endDate]);
    }
}
