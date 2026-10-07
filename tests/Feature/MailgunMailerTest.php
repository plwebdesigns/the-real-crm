<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Tests\TestCase;

class MailgunMailerTest extends TestCase
{
    public function test_mailgun_mailer_builds_an_http_transport(): void
    {
        config([
            'services.mailgun.domain' => 'mg.example.com',
            'services.mailgun.secret' => 'key-test',
            'services.mailgun.endpoint' => 'api.mailgun.net',
            'services.mailgun.scheme' => 'https',
        ]);

        $transport = Mail::mailer('mailgun')->getSymfonyTransport();

        $this->assertInstanceOf(MailgunHttpTransport::class, $transport);
    }
}
