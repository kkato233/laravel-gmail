<?php

namespace kkato233\LaravelGmail;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class GmailTransport extends AbstractTransport
{
    protected $config;
    protected $client;

    public function __construct(array $config)
    {
        $this->config = $config['mailers']['gmail'];

        $service_account_key = $this->config['service_account_key'];
        
        $SCOPES = implode(' ', [Gmail::GMAIL_SEND]);
        $APPLICATION_NAME = 'LaravelGmail';
        $GMAIL_CLIENT_SECRET_PATH = $_ENV["GMAIL_CLIENT_SECRET_PATH"];

        $this->client = new Client();
        $this->client->setApplicationName($APPLICATION_NAME);
        $this->client->setScopes($SCOPES);
        $this->client->setAuthConfig($GMAIL_CLIENT_SECRET_PATH);
        $this->client->setAccessType('online');
        
        $accessToken = json_decode(file_get_contents($service_account_key), true);
        $this->client->setAccessToken($accessToken);
        // リフレッシュトークンを使ってアクセストークンを更新する
        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();
            if (!$refreshToken) {
                throw new Exception("refresh token not found in " . $service_account_key);
            }

            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents($service_account_key, json_encode($this->client->getAccessToken()));
        }
        
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $service = new Gmail($this->client);

        $rawMessageString = $message->getOriginalMessage()->toString();

        $rawMessage = base64_encode($rawMessageString);
        $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage);
        
        $gmail_message = new Message();
        $gmail_message->setRaw($rawMessage);
        $results = $service->users_messages->send("me", $gmail_message);
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
