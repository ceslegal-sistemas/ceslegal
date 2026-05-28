# Gmail OAuth por Empresa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow each `Empresa` to connect its own Gmail account via Google OAuth2, so that emails sent from `CorreoEnviado` exit from that company's Gmail address instead of the system SMTP, with automatic SMTP fallback when OAuth is not connected or fails.

**Architecture:** Two migrations add OAuth token storage to `empresas` and a `empresa_id` FK to `correos_enviados`. Two focused services handle the OAuth lifecycle (`GoogleOAuthService`) and Gmail API sending via RFC2822 (`GmailApiService`). A standard Laravel controller handles the redirect/callback. `EnviarCorreoOficialJob` gains Gmail-or-SMTP routing logic. `EmpresaResource` ViewRecord page gets inline connect/disconnect header actions.

**Tech Stack:** Laravel 12, Filament 3.2, Laravel Http facade (Guzzle), Symfony Mime (already in Laravel), Google OAuth2 REST API, Gmail API REST — no new packages required.

---

## File Map

| Action | File |
|---|---|
| Create | `database/migrations/2026_05_28_000002_add_google_oauth_to_empresas.php` |
| Create | `database/migrations/2026_05_28_000003_add_empresa_id_to_correos_enviados.php` |
| Create | `app/Services/GoogleOAuthService.php` |
| Create | `app/Services/GmailApiService.php` |
| Create | `app/Http/Controllers/GoogleOAuthController.php` |
| Create | `tests/Unit/EmpresaGmailTest.php` |
| Create | `tests/Unit/GoogleOAuthServiceTest.php` |
| Create | `tests/Unit/GmailApiServiceTest.php` |
| Modify | `config/services.php` |
| Modify | `app/Models/Empresa.php` |
| Modify | `app/Models/CorreoEnviado.php` |
| Modify | `app/Jobs/EnviarCorreoOficialJob.php` |
| Modify | `routes/web.php` |
| Modify | `app/Filament/Admin/Resources/EmpresaResource/Pages/ViewEmpresa.php` |
| Modify | `app/Filament/Admin/Resources/CorreoEnviadoResource.php` |

---

### Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_05_28_000002_add_google_oauth_to_empresas.php`
- Create: `database/migrations/2026_05_28_000003_add_empresa_id_to_correos_enviados.php`

- [ ] **Step 1: Create the OAuth columns migration for empresas**

Create `database/migrations/2026_05_28_000002_add_google_oauth_to_empresas.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('google_oauth_email', 255)->nullable()->after('email_contacto');
            $table->text('google_oauth_tokens')->nullable()->after('google_oauth_email');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['google_oauth_email', 'google_oauth_tokens']);
        });
    }
};
```

- [ ] **Step 2: Create the empresa_id FK migration for correos_enviados**

Create `database/migrations/2026_05_28_000003_add_empresa_id_to_correos_enviados.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->foreignId('empresa_id')
                ->nullable()
                ->after('proceso_id')
                ->constrained('empresas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected output includes:
```
Migrated:  2026_05_28_000002_add_google_oauth_to_empresas (Xms)
Migrated:  2026_05_28_000003_add_empresa_id_to_correos_enviados (Xms)
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_28_000002_add_google_oauth_to_empresas.php database/migrations/2026_05_28_000003_add_empresa_id_to_correos_enviados.php
git commit -m "feat: add google_oauth columns to empresas and empresa_id to correos_enviados"
```

---

### Task 2: Config — Google block

**Files:**
- Modify: `config/services.php`

- [ ] **Step 1: Add Google block**

In `config/services.php`, add the following block after the `'slack'` entry (around line 36):

```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri'  => env('GOOGLE_REDIRECT_URI'),
],
```

- [ ] **Step 2: Commit**

```bash
git add config/services.php
git commit -m "feat: add Google OAuth config block to services.php"
```

---

### Task 3: Empresa model — OAuth fields and tieneGmailConectado()

**Files:**
- Modify: `app/Models/Empresa.php`
- Create: `tests/Unit/EmpresaGmailTest.php`

The `'encrypted'` cast in Laravel auto-calls `Crypt::encryptString()` on write and `Crypt::decryptString()` on read, so the service code never needs to call `encrypt()`/`decrypt()` directly.

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/EmpresaGmailTest.php`:

```php
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
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Unit/EmpresaGmailTest.php
```

Expected: FAIL — method `tieneGmailConectado` not found on Empresa.

- [ ] **Step 3: Update Empresa model**

In `app/Models/Empresa.php`:

**Add to `$fillable` array** (after `'actividad_economica_id'`):
```php
'google_oauth_email',
'google_oauth_tokens',
```

**Add to `$casts` array** (after `'active' => 'boolean'`):
```php
'google_oauth_tokens' => 'encrypted',
```

**Add these two methods** after the `tieneSuscripcionActiva()` method:
```php
public function tieneGmailConectado(): bool
{
    return !empty($this->google_oauth_tokens);
}

public function correos(): HasMany
{
    return $this->hasMany(\App\Models\CorreoEnviado::class);
}
```

The `HasMany` import is already at the top of the file.

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Unit/EmpresaGmailTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Empresa.php tests/Unit/EmpresaGmailTest.php
git commit -m "feat: add Google OAuth fields and tieneGmailConectado() to Empresa model"
```

---

### Task 4: GoogleOAuthService

**Files:**
- Create: `app/Services/GoogleOAuthService.php`
- Create: `tests/Unit/GoogleOAuthServiceTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/GoogleOAuthServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Services\GoogleOAuthService;
use Tests\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    public function test_build_auth_url_contains_required_params(): void
    {
        config([
            'services.google.client_id'    => 'test-client-id',
            'services.google.redirect_uri' => 'https://example.com/callback',
        ]);

        $service = new GoogleOAuthService();
        $url     = $service->buildAuthUrl(42);

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('gmail.send', urldecode($url));
        $this->assertStringContainsString('offline', $url);
        $this->assertStringContainsString('consent', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertEquals('test-client-id', $params['client_id']);
        $this->assertEquals(42, decrypt($params['state']));
    }

    public function test_get_valid_access_token_returns_existing_when_not_expired(): void
    {
        $empresa = new Empresa();
        // Setting through the model attribute triggers the encrypted cast
        $empresa->google_oauth_tokens = json_encode([
            'access_token'  => 'ya29.existing_token',
            'refresh_token' => '1//refresh',
            'expires_at'    => time() + 3600,
        ]);

        $service = new GoogleOAuthService();
        $token   = $service->getValidAccessToken($empresa);

        $this->assertEquals('ya29.existing_token', $token);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Unit/GoogleOAuthServiceTest.php
```

Expected: FAIL — class `GoogleOAuthService` not found.

- [ ] **Step 3: Create GoogleOAuthService**

Create `app/Services/GoogleOAuthService.php`:

```php
<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    private const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const SCOPE      = 'https://www.googleapis.com/auth/gmail.send';

    public function buildAuthUrl(int $empresaId): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => encrypt($empresaId),
        ]);
    }

    public function exchangeCode(string $code, string $encryptedState): Empresa
    {
        $empresaId = (int) decrypt($encryptedState);
        $empresa   = Empresa::findOrFail($empresaId);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'grant_type'    => 'authorization_code',
        ]);

        $response->throw();
        $data = $response->json();

        $empresa->update([
            'google_oauth_email'  => $this->fetchGmailEmail($data['access_token']),
            'google_oauth_tokens' => json_encode([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => time() + (int) $data['expires_in'],
            ]),
        ]);

        return $empresa->fresh();
    }

    public function getValidAccessToken(Empresa $empresa): string
    {
        $tokens = json_decode($empresa->google_oauth_tokens, true);

        if ($tokens['expires_at'] > (time() + 60)) {
            return $tokens['access_token'];
        }

        return $this->refreshAccessToken($empresa);
    }

    public function refreshAccessToken(Empresa $empresa): string
    {
        $tokens = json_decode($empresa->google_oauth_tokens, true);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $tokens['refresh_token'],
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type'    => 'refresh_token',
        ]);

        $response->throw();
        $data = $response->json();

        $tokens['access_token'] = $data['access_token'];
        $tokens['expires_at']   = time() + (int) $data['expires_in'];

        $empresa->update(['google_oauth_tokens' => json_encode($tokens)]);

        return $data['access_token'];
    }

    public function disconnect(Empresa $empresa): void
    {
        try {
            $tokens = json_decode($empresa->google_oauth_tokens, true);
            Http::post(self::REVOKE_URL . '?token=' . $tokens['refresh_token']);
        } catch (\Throwable) {
            // Revocation failure is non-critical; clear locally regardless
        }

        $empresa->update([
            'google_oauth_email'  => null,
            'google_oauth_tokens' => null,
        ]);
    }

    private function fetchGmailEmail(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        return $response->ok() ? ($response->json('email') ?? '') : '';
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Unit/GoogleOAuthServiceTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/GoogleOAuthService.php tests/Unit/GoogleOAuthServiceTest.php
git commit -m "feat: implement GoogleOAuthService with token management and auto-refresh"
```

---

### Task 5: GmailApiService

**Files:**
- Create: `app/Services/GmailApiService.php`
- Create: `tests/Unit/GmailApiServiceTest.php`

The service uses `Symfony\Component\Mime\Email::toString()` to produce a full RFC2822 message (headers + blank line + body), then base64url-encodes it for the Gmail API. Symfony Mime is already a Laravel dependency — no new packages needed.

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/GmailApiServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Unit/GmailApiServiceTest.php
```

Expected: FAIL — class `GmailApiService` not found.

- [ ] **Step 3: Create GmailApiService**

Create `app/Services/GmailApiService.php`:

```php
<?php

namespace App\Services;

use App\Models\CorreoEnviado;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

class GmailApiService
{
    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function send(CorreoEnviado $correo, string $accessToken): void
    {
        $email = (new Email())
            ->from($correo->empresa->google_oauth_email)
            ->to($correo->email_destinatario)
            ->subject($this->buildSubject($correo))
            ->html($this->buildHtmlBody($correo));

        foreach ($correo->email_cc ?? [] as $cc) {
            $email->addCc($cc);
        }

        foreach ($correo->adjuntos ?? [] as $path) {
            $absolutePath = Storage::disk('local')->path($path);
            if (file_exists($absolutePath)) {
                $email->attachFromPath($absolutePath, basename($path));
            }
        }

        $encoded = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->post(self::SEND_URL, ['raw' => $encoded]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Gmail API send failed (' . $response->status() . '): ' . $response->body()
            );
        }
    }

    private function buildSubject(CorreoEnviado $correo): string
    {
        return match ($correo->prioridad) {
            'urgente' => '[URGENTE] ' . $correo->asunto,
            'alta'    => '[IMPORTANTE] ' . $correo->asunto,
            default   => $correo->asunto,
        };
    }

    private function buildHtmlBody(CorreoEnviado $correo): string
    {
        $trackingUrl = route('correo.tracking.pixel', $correo->token);

        return view('mail.correo-oficial', [
            'correo'      => $correo,
            'trackingUrl' => $trackingUrl,
        ])->render();
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Unit/GmailApiServiceTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/GmailApiService.php tests/Unit/GmailApiServiceTest.php
git commit -m "feat: implement GmailApiService — RFC2822 encoding and Gmail API REST send"
```

---

### Task 6: GoogleOAuthController + Routes

**Files:**
- Create: `app/Http/Controllers/GoogleOAuthController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create GoogleOAuthController**

Create `app/Http/Controllers/GoogleOAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Services\GoogleOAuthService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleOAuthController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $oauthService) {}

    public function iniciar(int $empresaId): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'abogado']),
            403
        );

        abort_unless(Empresa::where('id', $empresaId)->exists(), 404);

        return redirect($this->oauthService->buildAuthUrl($empresaId));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            Notification::make()
                ->danger()
                ->title('Error al conectar Gmail')
                ->body((string) $request->string('error'))
                ->sendToDatabase(auth()->user());

            return redirect()->route('filament.admin.resources.empresas.index');
        }

        try {
            $empresa = $this->oauthService->exchangeCode(
                (string) $request->string('code'),
                (string) $request->string('state'),
            );

            Notification::make()
                ->success()
                ->title('Gmail conectado exitosamente')
                ->body("Cuenta {$empresa->google_oauth_email} vinculada a la empresa.")
                ->sendToDatabase(auth()->user());

            return redirect()->route(
                'filament.admin.resources.empresas.view',
                ['record' => $empresa->id]
            );
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback error', ['error' => $e->getMessage()]);

            Notification::make()
                ->danger()
                ->title('No se pudo conectar Gmail')
                ->body('Ocurrió un error al completar la conexión. Intente nuevamente.')
                ->sendToDatabase(auth()->user());

            return redirect()->route('filament.admin.resources.empresas.index');
        }
    }
}
```

- [ ] **Step 2: Add OAuth routes to web.php**

In `routes/web.php`, add the following block before the `// Servir documentación estática de Starlight` comment (near line 219):

```php
// Google OAuth para Gmail por empresa
Route::middleware('auth')->group(function () {
    Route::get('/google/oauth/iniciar/{empresaId}', [\App\Http\Controllers\GoogleOAuthController::class, 'iniciar'])
        ->name('google.oauth.iniciar');
    Route::get('/google/oauth/callback', [\App\Http\Controllers\GoogleOAuthController::class, 'callback'])
        ->name('google.oauth.callback');
});
```

- [ ] **Step 3: Verify routes are registered**

```bash
php artisan route:list --name=google.oauth
```

Expected output:
```
GET|HEAD  google/oauth/callback        google.oauth.callback
GET|HEAD  google/oauth/iniciar/{...}   google.oauth.iniciar
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/GoogleOAuthController.php routes/web.php
git commit -m "feat: add GoogleOAuthController and OAuth routes for Gmail connection"
```

---

### Task 7: CorreoEnviado model — empresa_id

**Files:**
- Modify: `app/Models/CorreoEnviado.php`

- [ ] **Step 1: Add empresa_id to fillable and add empresa() relation**

In `app/Models/CorreoEnviado.php`:

**Add `'empresa_id'` to `$fillable`** (after `'proceso_id'`, around line 22):
```php
'empresa_id',
```

**Add the `empresa()` relation method** after the `proceso()` method (around line 57):
```php
public function empresa(): BelongsTo
{
    return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
}
```

The `BelongsTo` import is already present at the top of the file.

- [ ] **Step 2: Verify with tinker**

```bash
php artisan tinker --execute="var_dump(in_array('empresa_id', (new App\Models\CorreoEnviado)->getFillable()));"
```

Expected: `bool(true)`

- [ ] **Step 3: Commit**

```bash
git add app/Models/CorreoEnviado.php
git commit -m "feat: add empresa_id FK and empresa() relation to CorreoEnviado model"
```

---

### Task 8: EnviarCorreoOficialJob — Gmail routing

**Files:**
- Modify: `app/Jobs/EnviarCorreoOficialJob.php`

- [ ] **Step 1: Replace handle() with Gmail-aware routing logic**

In `app/Jobs/EnviarCorreoOficialJob.php`, replace the entire `handle()` method with:

```php
public function handle(): void
{
    $correo = $this->correo->fresh();

    if (!$correo) {
        Log::warning('EnviarCorreoOficialJob: registro no encontrado', [
            'correo_id' => $this->correo->id,
        ]);
        return;
    }

    // Resolve empresa by priority: explicit > trabajador's > proceso's
    $empresaId = $correo->empresa_id
        ?? $correo->trabajador?->empresa_id
        ?? $correo->proceso?->empresa_id;

    $empresa  = $empresaId ? \App\Models\Empresa::find($empresaId) : null;
    $viaGmail = false;

    if ($empresa && $empresa->tieneGmailConectado()) {
        try {
            $accessToken = app(\App\Services\GoogleOAuthService::class)->getValidAccessToken($empresa);
            app(\App\Services\GmailApiService::class)->send($correo, $accessToken);
            $viaGmail = true;
        } catch (\Throwable $e) {
            Log::warning('Gmail API falló, usando SMTP como fallback', [
                'correo_id' => $correo->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    if (!$viaGmail) {
        Mail::to($correo->email_destinatario, $correo->destinatario_nombre)
            ->send(new CorreoOficial($correo));
    }

    $correo->update(['enviado_en' => now('America/Bogota')]);

    Log::info('Correo oficial enviado', [
        'correo_id'    => $correo->id,
        'destinatario' => $correo->email_destinatario,
        'via'          => $viaGmail ? 'gmail_oauth' : 'smtp',
    ]);
}
```

All required imports (`Mail`, `CorreoOficial`, `Log`) are already at the top of the file.

- [ ] **Step 2: Verify no syntax errors**

```bash
php artisan config:clear && php artisan tinker --execute="echo class_exists(App\Jobs\EnviarCorreoOficialJob::class) ? 'OK' : 'ERROR';"
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/EnviarCorreoOficialJob.php
git commit -m "feat: route EnviarCorreoOficialJob through Gmail OAuth when empresa is connected"
```

---

### Task 9: ViewEmpresa — Gmail connect/disconnect actions

**Files:**
- Modify: `app/Filament/Admin/Resources/EmpresaResource/Pages/ViewEmpresa.php`

The connect button redirects to `/google/oauth/iniciar/{id}` (a standard redirect — not a modal). The disconnect button runs inline via a Livewire action with a confirmation modal.

- [ ] **Step 1: Replace ViewEmpresa with Gmail-aware version**

Replace the entire content of `app/Filament/Admin/Resources/EmpresaResource/Pages/ViewEmpresa.php`:

```php
<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Services\GoogleOAuthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmpresa extends ViewRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('conectar_gmail')
                ->label('Conectar Gmail')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->url(fn () => route('google.oauth.iniciar', $this->record->id))
                ->visible(fn () => !$this->record->tieneGmailConectado()),

            Actions\Action::make('desconectar_gmail')
                ->label(fn () => 'Gmail: ' . $this->record->google_oauth_email)
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Desconectar Gmail')
                ->modalDescription('¿Está seguro de que desea desconectar la cuenta de Gmail de esta empresa? Los correos futuros se enviarán por SMTP.')
                ->action(function () {
                    app(GoogleOAuthService::class)->disconnect($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Gmail desconectado')
                        ->body('La cuenta ha sido desvinculada. Los correos futuros se enviarán por SMTP.')
                        ->send();
                })
                ->visible(fn () => $this->record->tieneGmailConectado()),
        ];
    }
}
```

- [ ] **Step 2: Verify the page loads**

Open `/admin/empresas/{any-id}` in the browser.

Expected:
- Page loads without errors
- Header shows **"Conectar Gmail"** button (green) when no Gmail is connected
- After connecting, header shows **"Gmail: email@gmail.com"** button (red) with disconnect option

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Admin/Resources/EmpresaResource/Pages/ViewEmpresa.php
git commit -m "feat: add Gmail connect/disconnect actions to EmpresaResource ViewRecord"
```

---

### Task 10: CorreoEnviadoResource — empresa_id field

**Files:**
- Modify: `app/Filament/Admin/Resources/CorreoEnviadoResource.php`

- [ ] **Step 1: Add empresa_id select to the form**

In `app/Filament/Admin/Resources/CorreoEnviadoResource.php`, inside the `'Correo'` section, add the following `Select` field **after the `proceso_id` select** (around line 97):

```php
Forms\Components\Select::make('empresa_id')
    ->label('Empresa remitente (Gmail)')
    ->relationship('empresa', 'razon_social')
    ->searchable()
    ->nullable()
    ->placeholder('Sistema (SMTP por defecto)')
    ->helperText('Si la empresa tiene Gmail conectado, el correo saldrá desde ese Gmail.'),
```

- [ ] **Step 2: Verify the form renders**

Navigate to `/admin/correo-enviados/create` in the browser.

Expected:
- Form loads without errors
- "Empresa remitente (Gmail)" select appears in the Correo section, between proceso and prioridad
- Selecting an empresa shows its razón social

- [ ] **Step 3: Run all unit tests**

```bash
php artisan test tests/Unit/
```

Expected: all 6 tests pass (EmpresaGmailTest ×2, GoogleOAuthServiceTest ×2, GmailApiServiceTest ×2).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Admin/Resources/CorreoEnviadoResource.php
git commit -m "feat: add empresa_id selector to CorreoEnviado create form"
```

---

## Post-implementation: .env setup reminder

Before testing the full OAuth flow end-to-end, add these keys to `.env` (values come from Google Cloud Console as described in the spec):

```
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxx
GOOGLE_REDIRECT_URI=https://{DOMINIO}/google/oauth/callback
```

The redirect URI must exactly match what was registered in Google Cloud Console → Credentials.
