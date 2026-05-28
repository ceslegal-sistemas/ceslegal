<?php

namespace Tests\Unit;

use App\Models\CorreoEnviado;
use App\Models\Empresa;
use App\Services\GmailApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailApiServiceTest extends TestCase
{
    public function test_send_posts_to_gmail_api_with_base64_encoded_message(): void
    {
        Http::fake([
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg123', 'labelIds' => ['SENT']], 200),
        ]);

        $empresa = new Empresa();
        $empresa->google_oauth_email = 'sender@empresa.com';

        $correo = new CorreoEnviado();
        $correo->token               = 'test-token-123';
        $correo->asunto              = 'Test Subject';
        $correo->cuerpo              = '<p>Test body</p>';
        $correo->prioridad           = 'normal';
        $correo->email_destinatario  = 'recipient@example.com';
        $correo->destinatario_nombre = 'Recipient Name';
        $correo->email_cc            = [];
        $correo->adjuntos            = [];
        $correo->setRelation('empresa', $empresa);

        (new GmailApiService())->send($correo, 'fake-access-token');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gmail.googleapis.com')
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && !empty($request->data()['raw']);
        });
    }

    public function test_send_throws_runtime_exception_on_gmail_api_error(): void
    {
        Http::fake([
            'gmail.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 401),
        ]);

        $empresa = new Empresa();
        $empresa->google_oauth_email = 'sender@empresa.com';

        $correo = new CorreoEnviado();
        $correo->token               = 'test-token';
        $correo->asunto              = 'Test';
        $correo->cuerpo              = '<p>Body</p>';
        $correo->prioridad           = 'normal';
        $correo->email_destinatario  = 'to@example.com';
        $correo->destinatario_nombre = 'Recipient';
        $correo->email_cc            = [];
        $correo->adjuntos            = [];
        $correo->setRelation('empresa', $empresa);

        $this->expectException(\RuntimeException::class);

        (new GmailApiService())->send($correo, 'bad-token');
    }
}
