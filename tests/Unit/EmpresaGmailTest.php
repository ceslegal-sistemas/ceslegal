<?php

namespace Tests\Unit;

use App\Models\Empresa;
use Tests\TestCase;

class EmpresaGmailTest extends TestCase
{
    public function test_tiene_gmail_conectado_returns_false_when_tokens_null(): void
    {
        $empresa = new Empresa();
        $empresa->google_oauth_tokens = null;

        $this->assertFalse($empresa->tieneGmailConectado());
    }

    public function test_tiene_gmail_conectado_returns_true_when_tokens_present(): void
    {
        $empresa = new Empresa();
        // Assigning through the model attribute goes through the encrypted cast
        $empresa->google_oauth_tokens = json_encode(['access_token' => 'ya29.test']);

        $this->assertTrue($empresa->tieneGmailConectado());
    }
}
