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
