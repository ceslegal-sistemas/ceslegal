<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\PayUService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuscripcionController extends Controller
{
    /**
     * Página de retorno después del checkout de PayU.
     * PayU redirige aquí con ?referenceCode=...&transactionState=...
     */
    public function retorno(Request $request, PayUService $payu)
    {
        $aprobado  = false;
        $rechazado = false;
        $mensaje   = '';

        // PayU retorna referenceCode y transactionState en la URL de respuesta
        $referencia        = $request->query('referenceCode');
        $transactionState  = $request->query('transactionState'); // 4=Approved, 6=Declined, etc.

        if (!$referencia) {
            return view('suscripcion.retorno', compact('aprobado', 'rechazado', 'mensaje'));
        }

        // Si PayU ya indicó el estado en la URL, usarlo directamente
        if ($transactionState === '4') {
            $aprobado = true;
            $mensaje  = '¡Pago exitoso! Su suscripción está siendo activada.';

            // Activar de forma idempotente (por si el webhook aún no llegó)
            $this->activarSiEsPosible($referencia, $payu);

        } elseif (in_array($transactionState, ['6', '104'])) {
            $rechazado = true;
            $mensaje   = 'El pago no fue aprobado. Por favor intente nuevamente.';

        } else {
            // Estado desconocido — consultar via API
            $payload = $payu->verificarTransaccion($referencia);

            if (!empty($payload)) {
                $txState = $payload[0]['transactions'][0]['transactionResponse']['state'] ?? '';
                if ($txState === 'APPROVED') {
                    $aprobado = true;
                    $mensaje  = '¡Pago exitoso! Su suscripción está activa.';
                    $this->activarSiEsPosible($referencia, $payu);
                } else {
                    $rechazado = true;
                    $mensaje   = 'El pago no fue aprobado. Por favor intente nuevamente.';
                }
            } else {
                $mensaje = 'Verificando el estado del pago. Si ya realizó el pago, su suscripción se activará automáticamente en minutos.';
            }
        }

        return view('suscripcion.retorno', compact('aprobado', 'rechazado', 'mensaje'));
    }

    private function activarSiEsPosible(string $referencia, PayUService $payu): void
    {
        $suscripcion = Suscripcion::where('payment_reference', $referencia)->first();

        if ($suscripcion && $suscripcion->estado !== 'activa') {
            $fechaFin = $suscripcion->ciclo_facturacion === 'anual'
                ? now()->addYear()
                : now()->addMonth();

            $suscripcion->update([
                'estado'      => 'activa',
                'fecha_inicio' => now(),
                'fecha_fin'   => $fechaFin,
            ]);

            Log::info('Suscripción activada en retorno', [
                'suscripcion_id' => $suscripcion->id,
                'empresa_id'     => $suscripcion->empresa_id,
            ]);
        }
    }
}
