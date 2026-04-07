<?php

namespace App\Services;

use App\Models\Suscripcion;
use Illuminate\Support\Facades\Http;

class PayUService
{
    private string $apiKey;
    private string $apiLogin;
    private string $merchantId;
    private string $accountId;
    private bool $sandbox;

    public function __construct()
    {
        $this->apiKey     = config('ces.payu.api_key', '');
        $this->apiLogin   = config('ces.payu.api_login', '');
        $this->merchantId = config('ces.payu.merchant_id', '');
        $this->accountId  = config('ces.payu.account_id', '');
        $this->sandbox    = (bool) config('ces.payu.sandbox', true);
    }

    /**
     * Construye la URL del checkout hosted de PayU.
     * El cliente es redirigido aquí para pagar via PSE/tarjeta/efectivo.
     */
    public function getCheckoutUrl(Suscripcion $suscripcion, string $buyerEmail, string $descripcion): string
    {
        $monto = $this->montoPorSuscripcion($suscripcion);
        $ref   = $suscripcion->payment_reference;
        $firma = $this->generarFirma($ref, $monto, 'COP');

        $baseUrl = $this->sandbox
            ? 'https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/'
            : 'https://checkout.payulatam.com/ppp-web-gateway-payu/';

        $params = http_build_query([
            'merchantId'      => $this->merchantId,
            'accountId'       => $this->accountId,
            'description'     => $descripcion,
            'referenceCode'   => $ref,
            'amount'          => number_format($monto, 2, '.', ''),
            'tax'             => '0',
            'taxReturnBase'   => '0',
            'currency'        => 'COP',
            'signature'       => $firma,
            'test'            => $this->sandbox ? '1' : '0',
            'buyerEmail'      => $buyerEmail,
            'responseUrl'     => route('suscripcion.retorno'),
            'confirmationUrl' => route('payu.confirmacion'),
        ]);

        return $baseUrl . '?' . $params;
    }

    public function generarReferencia(int $empresaId): string
    {
        return 'CES-' . $empresaId . '-' . time();
    }

    /**
     * Firma para el checkout: md5(apiKey~merchantId~referenceCode~amount~currency)
     */
    public function generarFirma(string $referencia, float $monto, string $moneda): string
    {
        $montoFormateado = number_format($monto, 2, '.', '');
        return md5("{$this->apiKey}~{$this->merchantId}~{$referencia}~{$montoFormateado}~{$moneda}");
    }

    /**
     * Verifica la firma de la notificación de confirmación de PayU.
     * PayU envía: md5(apiKey~merchantId~referenceCode~amount~currency~state_pol)
     */
    public function verificarConfirmacion(array $data): bool
    {
        $referencia = $data['reference_sale'] ?? '';
        $monto      = $data['amount'] ?? '';
        $moneda     = $data['currency'] ?? '';
        $estado     = $data['state_pol'] ?? '';
        $firma      = $data['sign'] ?? '';

        // PayU trunca montos con .00 a 1 decimal
        if (str_ends_with((string) $monto, '.00')) {
            $monto = rtrim(rtrim((string) $monto, '0'), '.');
            $monto = $monto . '.0';
        }

        $esperada = md5("{$this->apiKey}~{$this->merchantId}~{$referencia}~{$monto}~{$moneda}~{$estado}");

        return hash_equals($esperada, strtolower($firma));
    }

    /**
     * Consulta el estado de una transacción via API de PayU.
     */
    public function verificarTransaccion(string $referencia): array
    {
        $baseUrl = $this->sandbox
            ? 'https://sandbox.api.payulatam.com/reports-api/4.0/service.cgi'
            : 'https://api.payulatam.com/reports-api/4.0/service.cgi';

        $payload = [
            'language'    => 'es',
            'command'     => 'ORDER_DETAIL_BY_REFERENCE_CODE',
            'merchant'    => [
                'apiLogin' => $this->apiLogin,
                'apiKey'   => $this->apiKey,
            ],
            'details'     => [
                'referenceCode' => $referencia,
            ],
            'test'        => $this->sandbox,
        ];

        $response = Http::post($baseUrl, $payload);

        if ($response->successful()) {
            return $response->json('result.payload', []);
        }

        return [];
    }

    /**
     * Calcula el monto de la suscripción según plan y ciclo.
     */
    public function montoPorSuscripcion(Suscripcion $suscripcion): float
    {
        $planes  = config('ces.planes');
        $plan    = $planes[$suscripcion->plan] ?? null;

        if (!$plan) {
            return 0;
        }

        return $suscripcion->ciclo_facturacion === 'anual'
            ? (float) $plan['precio_anual_cop']
            : (float) $plan['precio_mensual_cop'];
    }
}
