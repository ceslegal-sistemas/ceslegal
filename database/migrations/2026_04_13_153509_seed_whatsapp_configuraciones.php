<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $claves = [
            [
                'clave'       => 'whatsapp_habilitado',
                'valor'       => '0',
                'tipo'        => 'boolean',
                'descripcion' => 'Activa o desactiva el envío de notificaciones por WhatsApp',
                'categoria'   => 'whatsapp',
                'editable'    => true,
            ],
            [
                'clave'       => 'whatsapp_phone_number_id',
                'valor'       => '',
                'tipo'        => 'text',
                'descripcion' => 'ID del número de teléfono en Meta Business (Phone Number ID)',
                'categoria'   => 'whatsapp',
                'editable'    => true,
            ],
            [
                'clave'       => 'whatsapp_business_account_id',
                'valor'       => '',
                'tipo'        => 'text',
                'descripcion' => 'ID de la cuenta de WhatsApp Business (WABA ID)',
                'categoria'   => 'whatsapp',
                'editable'    => true,
            ],
            [
                'clave'       => 'whatsapp_access_token',
                'valor'       => '',
                'tipo'        => 'text',
                'descripcion' => 'Token de acceso del sistema de usuario de Meta (System User Token)',
                'categoria'   => 'whatsapp',
                'editable'    => true,
            ],
            [
                'clave'       => 'whatsapp_webhook_verify_token',
                'valor'       => 'ces_legal_whatsapp',
                'tipo'        => 'text',
                'descripcion' => 'Token de verificación para el webhook de Meta (puede ser cualquier cadena)',
                'categoria'   => 'whatsapp',
                'editable'    => true,
            ],
        ];

        foreach ($claves as $item) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => $item['clave']],
                array_merge($item, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('categoria', 'whatsapp')
            ->delete();
    }
};
