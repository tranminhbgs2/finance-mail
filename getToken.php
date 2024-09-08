<?php

require 'vendor/autoload.php';

use Google\Client as Google_Client;

$client = new Google_Client();
$client->setAuthConfig('./storage/app/google/client_secret.json');
$client->addScope(Google\Service\Gmail::MAIL_GOOGLE_COM);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// Thêm redirect URI
$client->setRedirectUri('http://localhost');

// Yêu cầu người dùng truy cập URL để lấy mã xác thực
$authUrl = $client->createAuthUrl();
printf("Open the following link in your browser:\n%s\n", $authUrl);
print 'Enter verification code: ';
$authCode = trim(fgets(STDIN));

// Trao đổi mã xác thực lấy token truy cập
$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

// Lưu token truy cập vào file
file_put_contents('./storage/app/google/token.json', json_encode($accessToken));
echo "Access token saved to token.json\n";
