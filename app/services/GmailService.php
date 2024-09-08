<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Google\Service\Gmail as Google_Service_Gmail;
use Google_Client;

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
            'amount' => '',
            'unit' => '',
            'content' => ''
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
                preg_match($pattern1, $text, $matches_amount , 0, 0);
                preg_match($pattern2, $text, $matches_unit , 0, 0);
                if (!empty($matches_amount)) {
                    $amount = $matches_amount[1];
                    $amount = str_replace(',', '', $amount);

                    $res['amount'] = (int)$amount;
                }
                if (!empty($matches_unit)) {
                    $res['unit'] = $matches_unit[1];
                    $res['content'] = $text;
                }
            }
        }
        return $res;
    }
}
