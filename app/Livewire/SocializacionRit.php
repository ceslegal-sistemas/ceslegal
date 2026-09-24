<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class SocializacionRit extends Component
{
    public Empresa $empresa;

    public string $etapa = 'documento';

    public string $tipoDocumento = 'CC';
    public string $numeroDocumento = '';
    public string $numeroDocumentoConfirmacion = '';

    public ?int $trabajadorId = null;
    public bool $trabajadorExistente = false;

    public string $nombres = '';
    public string $apellidos = '';
    public string $genero = '';
    public string $cargo = '';
    public string $cargoPersonalizado = '';
    public array $cargosDisponibles = [];
    public string $email = '';
    public string $emailConfirmacion = '';
    public string $telefono = '';
    public string $direccion = '';

    public bool $esPrimeraAceptacion = true;
    public array $cambiosRit = [];
    public string $ritActivoTextoCompleto = '';
    public array $temasRit = [];

    public array $quizPreguntas = [];
    public int $quizIndiceActual = 0;
    public bool $quizRespuestaIncorrecta = false;

    public bool $declaracionAceptada = false;

    public string $alertaAccesorios = '';
    public string $errorValidacionFoto = '';
    public bool $validandoFoto = false;

    public function mount(Empresa $empresa): void
    {
        $this->empresa = $empresa;

        if (!$this->resolverRitActivo()) {
            $this->etapa = 'sin_rit';
        }
    }

    /**
     * Resuelve el RIT activo de $this->empresa SIEMPRE con
     * withoutGlobalScope('bufeteOrEmpresa') - ReglamentoInterno usa el mismo
     * scope que Empresa/Trabajador (ScopedToBufeteOrEmpresa). Sin esto, si
     * un usuario de bufete tiene otra "empresa activa" seleccionada en el
     * topbar en el mismo navegador donde alguien abre este link público, la
     * relación `$empresa->reglamentoInterno` puede devolver null en vez del
     * RIT real. Nunca usar esa relación directamente en NINGÚN punto de
     * este componente - siempre pasar por este método.
     */
    private function resolverRitActivo(): ?ReglamentoInterno
    {
        return ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa->id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Mismo listado de cargos que usa la creación de Solicitud de Contrato
     * (SolicitudContratoResource::getCargosParaSelect(), organigrama del
     * RIT via ReglamentoInternoService::cargosDeEmpresa()) - PERO no se
     * reutiliza esa función directamente: internamente hace
     * ReglamentoInterno::where('empresa_id', ...) SIN
     * withoutGlobalScope('bufeteOrEmpresa'), lo cual es correcto en un
     * contexto autenticado del panel admin pero NO en esta ruta pública
     * (mismo Gotcha crítico #3 de este componente). Se replica la misma
     * lógica aquí usando resolverRitActivo(), que sí es scope-safe.
     */
    private function cargarCargosDisponibles(): void
    {
        $rit = $this->resolverRitActivo();
        $organigrama = $rit
            ? ($rit->respuestas_cuestionario['cargos'] ?? $rit->organigrama ?? [])
            : [];

        $cargos = [];
        foreach ($organigrama as $item) {
            $nombre = trim((string) ($item['nombre_cargo'] ?? ''));
            if ($nombre !== '') {
                $cargos[$nombre] = $nombre;
            }
        }

        // Sin organigrama todavia (RIT de texto libre sin "Generar
        // organigrama" en Mi Reglamento Interno): mismo catalogo generico
        // de respaldo que usa Solicitud de Contrato - metodo puramente
        // estatico, sin consulta a BD ni dependencia de scope/sesion, por
        // eso es seguro reutilizarlo tal cual en esta ruta publica.
        if (empty($cargos)) {
            foreach (\App\Filament\Admin\Resources\SolicitudContratoResource::getCargos() as $cargo) {
                $cargos[$cargo] = $cargo;
            }
        }

        $this->cargosDisponibles = $cargos;
    }

    /**
     * Busca al trabajador SIEMPRE dentro de $this->empresa (resuelta por
     * token en el controlador) - el empresa_id nunca sale de un campo del
     * formulario, para no cruzar trabajadores entre empresas distintas.
     * withoutGlobalScope: mismo motivo que en SocializacionRitPublicoController.
     */
    public function buscarTrabajador(): void
    {
        // CC/CE/TI son siempre numéricos; pasaporte sí puede ser alfanumérico.
        $reglaNumero = $this->tipoDocumento === 'PASS'
            ? 'required|string|max:15'
            : 'required|digits_between:1,15';

        $this->validate([
            'tipoDocumento' => 'required|in:CC,CE,TI,PASS',
            'numeroDocumento' => $reglaNumero,
            'numeroDocumentoConfirmacion' => 'required|same:numeroDocumento',
        ], [
            'numeroDocumentoConfirmacion.same' => 'El número no coincide. Verifica que sea igual arriba y abajo.',
        ]);

        $this->cargarCargosDisponibles();

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa->id)
            ->where('tipo_documento', $this->tipoDocumento)
            ->where('numero_documento', $this->numeroDocumento)
            ->first();

        if ($trabajador) {
            $this->trabajadorId = $trabajador->id;
            $this->trabajadorExistente = true;
            $this->nombres = $trabajador->nombres;
            $this->apellidos = $trabajador->apellidos;
            $this->genero = $trabajador->genero;
            $this->email = (string) $trabajador->email;
            $this->telefono = (string) $trabajador->telefono;
            $this->direccion = (string) $trabajador->direccion;

            // Si el cargo ya guardado no esta en la lista actual del
            // organigrama (texto libre de antes, o el RIT cambio de
            // cargos), cae a "Otro" precargado en vez de perder el dato.
            if (array_key_exists($trabajador->cargo, $this->cargosDisponibles)) {
                $this->cargo = $trabajador->cargo;
            } else {
                $this->cargo = '__otro__';
                $this->cargoPersonalizado = $trabajador->cargo;
            }

            if ($trabajador->aceptoRitVigente()) {
                $this->etapa = 'ya_acepto';
                return;
            }
        }

        $this->etapa = 'datos';
    }

    public function guardarDatos(): void
    {
        $this->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'genero' => 'required|string',
            'cargo' => 'required|string',
            'cargoPersonalizado' => $this->cargo === '__otro__' ? 'required|string|max:255' : 'nullable|string|max:255',
            'email' => 'required|email',
            'emailConfirmacion' => 'required|same:email',
            'telefono' => 'required|string|max:50',
            'direccion' => 'nullable|string',
        ], [
            'emailConfirmacion.same' => 'Los correos no coinciden. Verifica que sea igual arriba y abajo.',
        ]);

        $cargoFinal = $this->cargo === '__otro__' ? $this->cargoPersonalizado : $this->cargo;

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->updateOrCreate(
            [
                'empresa_id' => $this->empresa->id,
                'tipo_documento' => $this->tipoDocumento,
                'numero_documento' => $this->numeroDocumento,
            ],
            [
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'genero' => $this->genero,
                'cargo' => $cargoFinal,
                'email' => $this->email ?: null,
                'telefono' => $this->telefono ?: null,
                'direccion' => $this->direccion ?: null,
                'active' => true,
            ]
        );

        $this->trabajadorId = $trabajador->id;
        $this->etapa = 'foto';
    }

    /**
     * Pre-captura: detecta accesorios faciales (gorra, tapabocas, gafas
     * oscuras, etc.) antes de mostrar el preview - mismo servicio y mismo
     * criterio fail-open que FormularioDescargos::verificarAccesorios().
     * Esta foto se guarda como foto_referencia_path del trabajador, que
     * luego AWS Rekognition usa en descargos para confirmar que es la misma
     * persona - por eso importa que quede sin accesorios que tapen el rostro.
     */
    public function verificarAccesorios(string $base64): void
    {
        $this->alertaAccesorios = '';
        try {
            $resultado = app(\App\Services\VerificacionFacialService::class)->detectarAccesorios($base64);
            if (!($resultado['ok'] ?? true)) {
                $this->alertaAccesorios = $resultado['motivo']
                    ?? 'Por favor retire cualquier accesorio que cubra su rostro antes de tomar la foto.';
            }
        } catch (\Throwable $e) {
            // fail-open: si falla la deteccion, se permite continuar
        }
    }

    /**
     * Punto de entrada real desde el front (Capa 2 - Gemini Vision valida
     * calidad: gafas, tapabocas, blur, etc.). No hay Capa 3 (Rekognition)
     * aqui: este es precisamente el paso que CREA foto_referencia_path, no
     * hay nada contra qué comparar todavia.
     */
    public function validarFotoConIA(string $fotoBase64): void
    {
        $this->validandoFoto = true;
        $this->errorValidacionFoto = '';

        $resultado = app(\App\Services\VerificacionFacialService::class)->validarCalidadFoto($fotoBase64);

        if (!$resultado['ok']) {
            $this->validandoFoto = false;
            $this->errorValidacionFoto = $resultado['motivo']
                ?? 'La foto no cumple los requisitos. Por favor intente de nuevo.';
            return;
        }

        $this->validandoFoto = false;
        $this->guardarFotoSimple($fotoBase64);
    }

    public function guardarFotoSimple(string $fotoBase64): void
    {
        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->findOrFail($this->trabajadorId);

        // Nunca sobrescribir una foto de referencia ya existente (podria
        // haber sido tomada antes con mejor calidad, ej. subida manual por
        // el admin) - solo se llena si estaba vacia.
        if (!$trabajador->foto_referencia_path) {
            [, $datos] = explode(',', $fotoBase64, 2);
            $contenido = base64_decode($datos);
            $ruta = 'fotos_referencia/' . $trabajador->id . '_' . Str::random(8) . '.jpg';
            Storage::disk('local')->put($ruta, $contenido);

            $trabajador->update(['foto_referencia_path' => $ruta]);
        }

        $trabajadorObj = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->find($this->trabajadorId);
        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        $ultimaAceptacion = $trabajadorObj?->aceptacionesReglamentoInterno()
            ->latest('aceptado_en')
            ->first();

        $this->ritActivoTextoCompleto = (string) $ritActivo->texto_completo;
        // Legal Design: nadie lee el reglamento completo en este paso - se
        // muestra, por cada tema clasificado, el resumen ESPECIFICO que la
        // IA ya genero sobre lo que ESTE RIT dice (ver
        // TemaClasificadorService::asegurarResumenesSimples(), calculado al
        // guardar el RIT, no aqui) - con respaldo a la descripcion generica
        // del tema si por algun motivo (fallo de IA, RIT recien migrado)
        // todavia no tiene resumen propio.
        $this->temasRit = $ritActivo->temasNormativos()
            ->activos()
            ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion'])
            ->map(fn ($tema) => [
                'nombre' => $tema->nombre,
                'descripcion' => $tema->pivot->resumen_simple ?: $tema->descripcion,
            ])
            ->all();

        if ($ultimaAceptacion) {
            $this->esPrimeraAceptacion = false;

            // $ultimaAceptacion->reglamentoInterno (belongsTo) dispararía
            // OTRA consulta sin proteger contra ReglamentoInterno::
            // ScopedToBufeteOrEmpresa - mismo riesgo del Gotcha crítico #3,
            // esta vez en una relación belongsTo en vez de hasOne. Siempre
            // resolver por id con withoutGlobalScope explícito, nunca vía
            // la relación directa, en NINGÚN modelo que use ese scope.
            $versionAnterior = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
                ->find($ultimaAceptacion->reglamento_interno_id);

            $this->cambiosRit = app(\App\Services\RitDiffService::class)->compararDocumentos(
                (string) $versionAnterior->texto_completo,
                $this->ritActivoTextoCompleto
            );
        } else {
            $this->esPrimeraAceptacion = true;
        }

        $this->etapa = 'presentacion_rit';
    }

    /**
     * Se llama al salir de 'presentacion_rit'. Elige 3 temas al azar entre
     * los que tienen pregunta_vf generada para el RIT activo - si hay
     * menos de 3 usa los que haya, si hay 0 salta la etapa entera
     * (fail-open: un fallo de IA generando el quiz nunca debe bloquear la
     * aceptación real del reglamento).
     */
    public function iniciarQuiz(): void
    {
        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3

        $this->quizPreguntas = $ritActivo->temasNormativos()
            ->activos()
            ->wherePivotNotNull('pregunta_vf')
            ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion'])
            ->map(fn ($tema) => [
                'pregunta' => $tema->pivot->pregunta_vf,
                'respuesta_correcta' => (bool) $tema->pivot->respuesta_correcta,
                'explicacion' => $tema->pivot->resumen_simple ?: $tema->descripcion,
            ])
            ->shuffle()
            ->take(3)
            ->values()
            ->all();

        $this->quizIndiceActual = 0;
        $this->quizRespuestaIncorrecta = false;

        $this->etapa = empty($this->quizPreguntas) ? 'aceptacion' : 'quiz';
    }

    /**
     * Sin límite de intentos (decisión explícita del spec): si falla,
     * queda en la MISMA pregunta con quizRespuestaIncorrecta=true (la vista
     * muestra la explicación) hasta que marque la correcta.
     */
    public function responderQuiz(bool $respuesta): void
    {
        $preguntaActual = $this->quizPreguntas[$this->quizIndiceActual] ?? null;
        if (!$preguntaActual) {
            return;
        }

        if ($respuesta !== $preguntaActual['respuesta_correcta']) {
            $this->quizRespuestaIncorrecta = true;
            return;
        }

        $this->quizRespuestaIncorrecta = false;
        $this->quizIndiceActual++;

        if ($this->quizIndiceActual >= count($this->quizPreguntas)) {
            $this->etapa = 'aceptacion';
        }
    }

    public function aceptarReglamento(): void
    {
        $this->validate([
            'declaracionAceptada' => 'accepted',
        ]);

        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3

        \App\Models\AceptacionReglamentoInterno::updateOrCreate(
            [
                'trabajador_id' => $this->trabajadorId,
                'reglamento_interno_id' => $ritActivo->id,
            ],
            [
                'aceptado_en' => now(),
                'ip_aceptacion' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]
        );

        app(\App\Services\LogroSocializacionRitService::class)->revisarYOtorgar($this->empresa);

        // Fail-open: si el correo falla (SMTP caido, direccion invalida
        // que paso la validacion basica, etc.) no debe bloquear el registro
        // ya guardado - el trabajador ya quedo aceptado en la BD.
        if ($this->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($this->email)->send(
                    new \App\Mail\RitAceptado(trim("{$this->nombres} {$this->apellidos}"), $this->empresa->razon_social)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SocializacionRit: fallo al enviar correo de confirmacion', [
                    'trabajador_id' => $this->trabajadorId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->etapa = 'completado';
    }

    public function render()
    {
        return view('livewire.socializacion-rit');
    }
}
