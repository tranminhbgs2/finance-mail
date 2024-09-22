<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Google\Service\Gmail as Google_Service_Gmail;
use Google_Client;
use Symfony\Component\DomCrawler\Crawler;

class GmailService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(storage_path('app/google/client_secret.json'));
        $this->client->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        // Load token truy cập từ file
        if (file_exists(storage_path('app/google/token.json'))) {
            $accessToken = json_decode(file_get_contents(storage_path('app/google/token.json')), true);
            $this->client->setAccessToken($accessToken);

            // Làm mới token nếu cần thiết
            if ($this->client->isAccessTokenExpired()) {
                $refreshToken = $this->client->getRefreshToken();
                $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                file_put_contents(storage_path('app/google/token.json'), json_encode($this->client->getAccessToken()));
            }
        } else {
            throw new \Exception('Token file not found. Please run getToken.php to generate the token.');
        }

        $this->service = new Google_Service_Gmail($this->client);
    }

    public function getGmailMessages($query = '')
    {
        $user = 'me'; // Đại diện tài khoản hiện tại
        $optParams = [];
        if ($query) {
            $optParams['q'] = $query;
        }
        $messages = $this->service->users_messages->listUsersMessages($user, $optParams);

        return $messages->getMessages();
    }

    public function getMessageDetails($messageId, $bank = 'HSBC')
    {
        $user = 'me';
        $message = $this->service->users_messages->get($user, $messageId);

        $payload = $message->getPayload();
        $headers = $payload->getHeaders();

        $details = [];
        foreach ($headers as $header) {
            if ($header->getName() == 'Subject') {
                $details['subject'] = $header->getValue();
            }
            if ($header->getName() == 'From') {
                $details['from'] = $header->getValue();
            }
        }

        $details['snippet'] = $message->getSnippet();
        $details['date'] = $message->getInternalDate();

        // Lấy toàn bộ nội dung email
        $details['body'] = $this->getBody($payload);
        $details['content'] = [];

        // Trích xuất nội dung cụ thể từ email HSBC
        switch ($bank) {
            case 'HSBC':
                $details['content'] = $this->extractContent($details['body']);
                break;
            case 'VPBANK':
                $details['content'] = $this->extractContentVpBank($details['body']);
                break;
            case 'BIDV':
                $details['content'] = $this->extractContentBIDV($details['body']);
                break;
            default:
                break;
        }

        return $details;
    }
    private function getBody($payload)
    {
        // Hàm giải mã Base64URL
        $decodeBase64Url = function ($data) {
            $data = str_replace(['-', '_'], ['+', '/'], $data);
            return base64_decode($data);
        };

        $body = '';

        // Xử lý nội dung chính của email
        if ($payload->getBody()->getSize() > 0) {
            $body = $payload->getBody()->getData();
            // Giải mã nội dung
            $body = $decodeBase64Url($body);
        } else {
            // Xử lý các phần của email nếu không có nội dung chính
            foreach ($payload->getParts() as $part) {
                $mimeType = $part->getMimeType();
                if ($mimeType == 'text/plain' || $mimeType == 'text/html') {
                    $partBody = $part->getBody()->getData();
                    // Giải mã nội dung
                    $body .= $decodeBase64Url($partBody);
                } elseif ($mimeType == 'multipart/alternative') {
                    foreach ($part->getParts() as $subPart) {
                        $subMimeType = $subPart->getMimeType();
                        if ($subMimeType == 'text/plain' || $subMimeType == 'text/html') {
                            $subPartBody = $subPart->getBody()->getData();
                            // Giải mã nội dung
                            $body .= $decodeBase64Url($subPartBody);
                        }
                    }
                }
            }
        }

        return $body;
    }

    // Hàm trích xuất nội dung cụ thể từ đoạn HTML với HSBC
    function extractContent($html)
    {

        $res = [
            'date_success' => '',
            'price' => '',
            'account_receiver' => '',
            'name_receiver' => '',
            'content_transfer' => '',
            'fee_amount' => '',
            'type' => 'ORTHER'
        ];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Đặt mã hóa UTF-8
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        // $dom->loadHTML($html);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!empty($errors)) {
            $a = "Có lỗi trong HTML:\n";
            foreach ($errors as $error) {
                $a .= $error->message . "\n";
            }
            return $res;
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//font[@style]");

        if ($nodes->length > 0) {
            $content = "";
            foreach ($nodes as $node) {
                $content .= trim($node->textContent);
            }
            $arr = explode("\n", $content);
            // Duyệt qua từng phần tử của mảng
            foreach ($arr as $text) {
                $text = str_replace('\t\t', '', $text);
                $text = trim($text);
                $pattern1 = '/số tiền\s([\d,]+)/';
                $pattern2 = '/tại\s(.+?)\s*vào ngày/';
                preg_match($pattern1, $text, $matches_amount, 0, 0);
                preg_match($pattern2, $text, $matches_unit, 0, 0);
                if (!empty($matches_amount)) {
                    $amount = $matches_amount[1];
                    $amount = str_replace(',', '', $amount);

                    $res['price'] = (int)$amount;
                }
                if (!empty($matches_unit)) {
                    $res['name_receiver'] = $matches_unit[1];
                    $res['content_transfer'] = checkType('mua sam');
                }
            }
        }

        return $res;
    }

    function extractContentVpBank($html)
    {
        $res = [
            'date_success' => '',
            'price' => '',
            'account_receiver' => '',
            'name_receiver' => '',
            'content_transfer' => '',
            'fee_amount' => '',
            'type' => 'ORTHER'
        ];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $td_elements = $dom->getElementsByTagName('td');

        foreach ($td_elements as $td) {
            $text = trim($td->textContent);

            // Extract Ngày, giờ giao dịch
            if (strpos($text, 'Ngày, giờ giao dịch:') !== false) {
                $res['date_success'] = trim($td->nextSibling->textContent);
                $res['date_success'] = str_replace('/', '-', $res['date_success']);
                $res['date_success'] = date('Y-m-d H:i:s', strtotime($res['date_success']));
            }

            // Extract Số tiền trích nợ
            if (strpos($text, 'Số tiền trích nợ:') !== false) {
                $res['price'] = trim($td->nextSibling->textContent);
                $res['price'] = str_replace(',', '', $res['price']);
                $res['price'] = str_replace(' VND', '', $res['price']);
                $res['price'] = (int)$res['price'];
            }

            if ($res['price'] == '') {
                // Extract Số tiền ghi có
                if (strpos($text, 'Số tiền thanh toán:') !== false) {
                    $res['price'] = trim($td->nextSibling->textContent);
                    $res['price'] = str_replace(',', '', $res['price']);
                    $res['price'] = str_replace(' VND', '', $res['price']);
                    $res['price'] = (int)$res['price'];
                }
            }

            // Extract Tài khoản ghi có
            if (strpos($text, 'Tài khoản ghi có:') !== false) {
                $res['account_receiver'] = trim($td->nextSibling->textContent);
            }

            // Extract Tên người hưởng
            if (strpos($text, 'Tên người hưởng:') !== false) {
                $res['name_receiver'] = trim($td->nextSibling->textContent);
            }

            if ($res['name_receiver'] == '') {
                // Extract Tên người nhận
                if (strpos($text, 'Dịch vụ thanh toán:') !== false) {
                    $res['name_receiver'] = trim($td->nextSibling->textContent);
                }
            }

            // Extract Nội dung chuyển tiền
            if (strpos($text, 'Nội dung chuyển tiền:') !== false) {
                $res['content_transfer'] = trim($td->nextSibling->textContent);
                $res['type'] = checkType($res['content_transfer']);
            }

            // Extract Số tiền phí
            if (strpos($text, 'Số tiền phí:') !== false) {
                $res['fee_amount'] = trim($td->nextSibling->textContent);
                $res['fee_amount'] = str_replace(',', '', $res['fee_amount']);
                $res['fee_amount'] = str_replace(' VND', '', $res['fee_amount']);
                $res['fee_amount'] = (int)$res['fee_amount'];
            }
        }

        return $res;
    }

    function extractContentBIDV($html)
    {
        $res = [
            'date_success' => '',
            'price' => '',
            'account_receiver' => '',
            'name_receiver' => '',
            'content_transfer' => '',
            'fee_amount' => '',
            'type' => 'ORTHER'
        ];

        $crawler = new Crawler($html);
        // Chọn bảng thứ 4 trong HTML
        $tables = $crawler->filter('table'); // Chỉ số bắt đầu từ 0, nên bảng thứ 4 có chỉ số là 3

        $foundTableHtml = null;

        $tables->each(function (Crawler $tableCrawler) use (&$foundTableHtml) {
            // Tìm <th> chứa "Loại giao dịch"
            if ($tableCrawler->filter('th:contains("Loại giao dịch")')->count() > 0) {
                $foundTableHtml = $tableCrawler->html(); // Lưu bảng khi tìm thấy
                return false; // Dừng lặp khi tìm thấy
            }
        });

        if ($foundTableHtml) {
            $crawler = new Crawler($foundTableHtml);

            $data = [];

            // Lặp qua từng cặp <th> và <td> trong bảng
            $crawler->filter('tr')->each(function (Crawler $node) use (&$data) {
                $thNode = $node->filter('th');
                $td = trim($node->filter('td')->text(''));

                // Kiểm tra nếu thẻ <span> tồn tại trong thẻ <th>
                if ($thNode->filter('span')->count() > 0) {
                    $th = trim($thNode->filter('span')->text('')); // Lấy text từ <span>
                } else {
                    $th = trim($thNode->text('')); // Nếu không có <span>, lấy text từ <th>
                }
                if ($th && $td) {
                    // Định dạng lại thẻ <th> thành khóa hợp lý
                    $formattedKey = $this->formatKey($th);
                    $data[$formattedKey] = $td;
                }
            });
            // Data Example
            // {
            //     "transaction_type": "Chuyển tiền nội bộ BIDV Within BIDV transfer",
            //     "transaction_time": "09/09/2024 11:57:16",
            //     "reference_number": "039osVc-7u4h5kqWg",
            //     "debit_account": "8851757371",
            //     "transaction_amount": "45,000 VND",
            //     "currency": "VND",
            //     "transaction_fee": "0 VND",
            //     "vat": "0 VND",
            //     "fee_payer": "Người chuyển",
            //     "beneficiary_name": "PHAM DINH VIET",
            //     "beneficiary_account_card_number": "2203436476",
            //     "beneficiary_bank": "BIDV",
            //     "credit_amount": "45,000 VND",
            //     "transaction_remark": "TRAN VAN MINH Chuyen tien",
            //     "channel": "MB",
            //     "operating_system": "ANDROID",
            //     "transaction_status": "Giao dịch thành công",
            //     "ip": "171.246.8.124"
            //   }

            $data['transaction_time'] = str_replace('/', '-', $data['transaction_time']);
            $res = [
                'date_success' => date('Y-m-d H:i:s', strtotime($data['transaction_time'])),
                'price' => str_replace(',', '', $data['transaction_amount']),
                'account_receiver' => $data['beneficiary_account_card_number'],
                'name_receiver' => $data['beneficiary_name'],
                'content_transfer' => $data['transaction_remark'],
                'fee_amount' => str_replace(',', '', $data['transaction_fee']),
                'type' => checkType($data['transaction_remark'])
            ];

            $res['price'] = str_replace(' VND', '', $res['price']);
            $res['price'] = (int)$res['price'];
            $res['fee_amount'] = str_replace(' VND', '', $res['fee_amount']);
            $res['fee_amount'] = (int)$res['fee_amount'];
            // Dữ liệu trích xuất
            return ['count' => 1, 'table_html' => $res]; // Trả về bảng tìm thấy
        } else {
            return ['error' => 'Không tìm thấy bảng phù hợp.']; // Thông báo lỗi
        }
    }

    function formatKey($key)
    {
        // Xóa dấu ':' và các khoảng trắng thừa
        $key = trim($key, ": ");

        // Chuyển đổi ký tự có dấu về không dấu
        $key = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $key));

        // Thay thế các khoảng trắng và dấu không phải chữ thành dấu gạch dưới
        $key = preg_replace('/[^a-z0-9]+/', '_', strtolower($key));

        return $key;
    }
    function removeAccents($str)
    {
        if (!extension_loaded('intl')) {
            // Nếu chưa có thư viện intl, cảnh báo người dùng
            throw new \Exception('PHP Intl extension is not enabled.');
        }

        // Sử dụng intl để chuyển đổi ký tự có dấu thành không dấu
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII', $str);

        return $str;
    }
}
