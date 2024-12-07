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

        $from_address = $this->config['from_address'];
        $service_account_key = $this->config['service_account_key'];
        
        $SCOPES = implode(' ', [Gmail::GMAIL_SEND]);
        $APPLICATION_NAME = 'LaravelGmail';

        $this->client = new Client();
        $this->client->setApplicationName($APPLICATION_NAME);
        $this->client->setScopes($SCOPES);
        $this->client->setAuthConfig($service_account_key);
        $this->client->setAccessType('offline');
        
        // ファイルが無い場合にブラウザから認証を行う
        if (!file_exists($service_account_key)) {
            $authUrl = $this->client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            if (!file_exists(dirname($service_account_key))) {
                mkdir(dirname($service_account_key), 0700, true);
            }
            file_put_contents($service_account_key, json_encode($accessToken));
            printf("Credentials saved to %s\n", $service_account_key);
        }
        $accessToken = json_decode(file_get_contents($service_account_key), true);
        $this->client->setAccessToken($accessToken);
        // リフレッシュトークンを使ってアクセストークンを更新する
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            file_put_contents($credentialsPath, json_encode($this->client->getAccessToken()));
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
