<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\PayUService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayUConfirmationController extends Controller
{
    /**
     * PayU llama a esta URL (POST) cuando se completa una transacción.
     * Es server-to-server — no requiere sesión del usuario.
     */
    public function handle(Request $request, PayUService $payu)
    {
        $data = $request->all();

        Log::info('PayU confirmación recibida', ['data' => $data]);

        if (!$payu->verificarConfirmacion($data)) {
            Log::warning('PayU confirmación: firma inválida', ['ip' => $request->ip()]);
            return response('FIRMA_INVALIDA', 200); // Siempre 200
        }

        $estadoPol  = $data['state_pol'] ?? '';
        $referencia = $data['reference_sale'] ?? null;

        // state_pol 4 = APPROVED
        if ($estadoPol === '4' && $referencia) {
            $suscripcion = Suscripcion::where('payment_reference', $referencia)->first();

            if ($suscripcion && $suscripcion->estado !== 'activa') {
                $ciclo     = $suscripcion->ciclo_facturacion;
                $fechaFin  = $ciclo === 'anual'
                    ? now()->addYear()
                    : now()->addMonth();

                $suscripcion->update([
                    'estado'                 => 'activa',
                    'payment_transaction_id' => $data['transaction_id'] ?? null,
                    'monto_pagado'           => $data['amount'] ?? null,
                    'fecha_inicio'           => now(),
                    'fecha_fin'              => $fechaFin,
                ]);

                Log::info('Suscripción activada via PayU', [
                    'suscripcion_id' => $suscripcion->id,
                    'empresa_id'     => $suscripcion->empresa_id,
                    'plan'           => $suscripcion->plan,
                    'ciclo'          => $ciclo,
                ]);
            }
        }

        return response('OK', 200);
    }
}
