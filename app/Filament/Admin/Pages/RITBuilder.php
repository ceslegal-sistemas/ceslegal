<?php

namespace App\Filament\Admin\Pages;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\RITGeneratorService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RITBuilder extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?string $navigationLabel = 'Construir RIT';

    protected static ?string $title = 'Constructor de Reglamento Interno de Trabajo';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.admin.pages.rit-builder';

    public static function getSlug(): string
    {
        return 'rit-builder';
    }

    // ── Estado del formulario ────────────────────────────────────────────────

    public array $respuestas = [
        // Sección 1: Datos generales
        'razon_social_completa'    => '',
        'nit_empresa'              => '',
        'domicilio_principal'      => '',
        'actividad_economica'      => '',
        'numero_trabajadores'      => '',
        'tiene_sucursales'         => '',
        'sucursales_detalle'       => '',

        // Sección 2: Estructura organizacional
        'jerarquia_cargos'         => '',
        'cargos_con_sancion'       => '',
        'tiene_manual_funciones'   => '',

        // Sección 3: Contratos
        'tipos_contrato_usados'    => '',
        'tiene_aprendices'         => '',
        'tiene_trabajadores_mision' => '',

        // Sección 4: Jornada laboral
        'horario_entrada'          => '',
        'horario_salida'           => '',
        'tiene_turnos'             => '',
        'descripcion_turnos'       => '',
        'control_asistencia'       => '',
        'trabaja_sabados'          => '',
        'trabaja_dominicales'      => '',
        'politica_horas_extras'    => '',

        // Sección 5: Salario y beneficios
        'forma_pago'               => '',
        'periodicidad_pago'        => '',
        'maneja_comisiones'        => '',
        'beneficios_extralegales'  => '',

        // Sección 6: Permisos y licencias
        'politica_permisos'        => '',
        'licencias_especiales'     => '',
        'politica_incapacidades'   => '',

        // Sección 7: Régimen disciplinario
        'ejemplos_faltas_leves'    => '',
        'ejemplos_faltas_graves'   => '',
        'ejemplos_faltas_muy_graves' => '',
        'sanciones_contempladas'   => '',

        // Sección 8: SST
        'tiene_sg_sst'             => '',
        'riesgos_principales'      => '',
        'epp_requeridos'           => '',

        // Sección 9: Conducta y equipos
        'politica_celular'         => '',
        'usa_uniforme'             => '',
        'tiene_codigo_etica'       => '',
        'politica_confidencialidad' => '',

        // Sección 10: Contexto y riesgos
        'problemas_disciplinarios_previos' => '',
        'que_quiere_prevenir'      => '',
        'otras_politicas'          => '',
    ];

    public bool $generando = false;

    public ?string $docxPath = null;

    public ?string $textoGenerado = null;

    public ?string $error = null;

    // ── Acceso ───────────────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user !== null && $user->hasAnyRole(['super_admin', 'abogado', 'cliente']);
    }

    // ── Mount ────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $empresa = $this->getEmpresa();

        if ($empresa) {
            // Pre-rellenar con datos conocidos de la empresa
            $this->respuestas['razon_social_completa'] = $empresa->razon_social ?? '';
            $this->respuestas['nit_empresa']           = $empresa->nit ?? '';
            $this->respuestas['domicilio_principal']   = trim(($empresa->direccion ?? '') . ' ' . ($empresa->ciudad ?? '') . ', ' . ($empresa->departamento ?? ''));
            $this->respuestas['trabaja_sabados']       = $empresa->trabajaSabados() ? 'Sí' : 'No';
        }
    }

    // ── Acción principal ─────────────────────────────────────────────────────

    public function construir(): void
    {
        $this->error         = null;
        $this->textoGenerado = null;
        $this->docxPath      = null;

        // Validación mínima
        $camposRequeridos = [
            'razon_social_completa' => 'Razón social',
            'actividad_economica'   => 'Actividad económica',
            'numero_trabajadores'   => 'Número de trabajadores',
            'horario_entrada'       => 'Horario de entrada',
            'horario_salida'        => 'Horario de salida',
            'forma_pago'            => 'Forma de pago',
        ];

        foreach ($camposRequeridos as $campo => $label) {
            if (empty(trim($this->respuestas[$campo] ?? ''))) {
                $this->error = "El campo \"{$label}\" es obligatorio para generar el Reglamento Interno.";
                Notification::make()
                    ->warning()
                    ->title('Campo requerido')
                    ->body($this->error)
                    ->send();
                return;
            }
        }

        $empresa = $this->getEmpresa();
        if (!$empresa) {
            $this->error = 'No se encontró la empresa asociada a su cuenta.';
            Notification::make()->danger()->title('Error')->body($this->error)->send();
            return;
        }

        $this->generando = true;

        try {
            $service = app(RITGeneratorService::class);

            // Generar texto con IA
            $textoRIT = $service->generarTextoRIT($this->respuestas, $empresa);

            // Guardar en BD
            ReglamentoInterno::updateOrCreate(
                ['empresa_id' => $empresa->id],
                [
                    'nombre'                 => 'Reglamento Interno generado con IA — ' . now()->format('d/m/Y'),
                    'texto_completo'         => $textoRIT,
                    'activo'                 => true,
                    'respuestas_cuestionario' => $this->respuestas,
                    'fuente'                 => 'construido_ia',
                ]
            );

            // Generar documento Word
            $rutaDocx = $service->generarDocumentoWord($textoRIT, $empresa);

            $this->textoGenerado = $textoRIT;
            $this->docxPath      = $rutaDocx;

            Notification::make()
                ->success()
                ->title('¡Reglamento Interno generado!')
                ->body('Su RIT fue redactado con IA y guardado. Puede descargarlo en formato Word.')
                ->send();

        } catch (\Exception $e) {
            Log::error('Error generando RIT con IA', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            $this->error = 'Ocurrió un error al generar el Reglamento Interno: ' . $e->getMessage();
            Notification::make()->danger()->title('Error')->body('No se pudo generar el RIT. Intente nuevamente.')->send();
        } finally {
            $this->generando = false;
        }
    }

    // ── Descarga ─────────────────────────────────────────────────────────────

    public function getDescargarUrl(): ?string
    {
        $empresa = $this->getEmpresa();
        if (!$empresa) return null;

        $user = Auth::user();
        $esAdmin = $user?->hasRole('super_admin') || $user?->hasRole('abogado');

        if (!$this->docxPath) {
            // Verificar si ya existe un RIT generado previamente
            $rit = ReglamentoInterno::where('empresa_id', $empresa->id)
                ->where('fuente', 'construido_ia')
                ->where('activo', true)
                ->latest()
                ->first();

            if (!$rit) return null;
        }

        return $esAdmin
            ? route('rit.descargar.admin', $empresa)
            : route('rit.descargar');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getEmpresa(): ?Empresa
    {
        $user = Auth::user();
        if (!$user) return null;

        if ($user->hasRole('super_admin') || $user->hasRole('abogado')) {
            return Empresa::first(); // Para admins, retornar primera empresa (uso interno)
        }

        return $user->empresa ?? null;
    }

    public function getEmpresaNombre(): string
    {
        return $this->getEmpresa()?->razon_social ?? 'Su empresa';
    }

    public function yaExisteRIT(): bool
    {
        $empresa = $this->getEmpresa();
        if (!$empresa) return false;

        return ReglamentoInterno::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->exists();
    }
}
