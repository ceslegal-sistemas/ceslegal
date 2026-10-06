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

    /** Token público de la empresa - se necesita aparte de $empresa para armar la URL firmada del video didáctico (rit.socializar.video). */
    public string $token = '';

    public string $etapa = 'documento';

    /**
     * 'publicacion' (Fase 1, ligera) o 'socializacion' (Fase 2, flujo
     * completo actual) - calculada en mount() a partir de
     * ReglamentoInterno::faseSocializacionActual(). Ver spec 2026-09-30.
     */
    public string $fase = 'publicacion';

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
    /**
     * Títulos de los capítulos del video didáctico, en orden (rediseño
     * 2026-09-30: cada tema/cambio es un clip independiente, no un solo
     * video) - vacío si el admin todavía no lo generó. Ver
     * ReglamentoInterno::capitulosVideoDidactico().
     *
     * @var array<int, string>
     */
    public array $capitulosVideoDidactico = [];

    public array $quizPreguntas = [];
    public int $quizIndiceActual = 0;
    public bool $quizRespuestaIncorrecta = false;

    /**
     * Evidencia jurídica (2026-09-28): a diferencia de $quizPreguntas (solo
     * el estado de la pregunta ACTUAL, transitorio), esto acumula CADA
     * intento de CADA pregunta con su resultado y timestamp - se persiste
     * completo en AceptacionReglamentoInterno.quiz_resultado al aceptar.
     *
     * @var array<int, array{pregunta: string, respuesta_correcta: bool, intentos: array<int, array{respuesta_dada: bool, correcta: bool, respondido_en: string}>}>
     */
    public array $quizRespuestas = [];

    public bool $declaracionAceptada = false;

    /**
     * Escalamiento a RRHH (pedido de Andrés Sarmiento, 2026-10-03): cuenta
     * cuántas veces el trabajador manifestó no entender el RIT, ya sea con
     * el botón manual o fallando muchas veces seguidas la misma pregunta
     * del quiz. A la 2da vez se bloquea el proceso y se avisa a RRHH - ver
     * marcarNoComprendio(). Solo aplica en Fase 2 (Socialización); Fase 1
     * no tiene quiz ni declaración fuerte de comprensión.
     */
    public int $vecesNoComprendio = 0;

    public string $alertaAccesorios = '';
    public string $errorValidacionFoto = '';
    public bool $validandoFoto = false;

    /**
     * Selfie tomada EN el momento de esta aceptación puntual (distinta de
     * foto_referencia_path del trabajador, que puede venir de una sesión
     * anterior sin relación con este acto - pedido explícito del usuario:
     * exigir una foto nueva en cada aceptación).
     */
    public string $fotoAceptacionBase64 = '';
    public string $errorValidacionFotoAceptacion = '';
    public bool $validandoFotoAceptacion = false;

    public function mount(Empresa $empresa, string $token = ''): void
    {
        $this->empresa = $empresa;
        $this->token = $token;

        $ritActivo = $this->resolverRitActivo();
        if (!$ritActivo) {
            $this->etapa = 'sin_rit';
            return;
        }

        $this->fase = $ritActivo->faseSocializacionActual();

        if ($this->token !== '') {
            $this->restaurarProgresoDesdeSesion();
        }
    }

    /**
     * Pedido del usuario (2026-10-05): refrescar el navegador en cualquier
     * punto del flujo mandaba al trabajador de vuelta a "ingrese su
     * cédula", perdiendo toda la sensación de avance ("como si hubiera
     * perdido todo lo que hizo") - Livewire reinstancia el componente desde
     * cero en cada carga de página, así que ninguna propiedad pública
     * sobrevive un F5 por sí sola. guardarDatos() guarda el trabajador_id en
     * la sesión de Laravel (que sí sobrevive un refresh, a diferencia del
     * estado en memoria de Livewire) y aquí se intenta restaurar - si ya
     * aceptó/confirmó mientras tanto (otra pestaña, por ejemplo) se respeta
     * ese estado final; si no, se reconstruye la pantalla 'presentacion_rit'
     * (video/resumen/texto) en vez de forzarlo a reingresar su documento y
     * sus datos personales desde cero. El quiz/declaración sí se rehacen -
     * aceptable, no hay forma segura de recordar en qué pregunta iba.
     */
    private function restaurarProgresoDesdeSesion(): void
    {
        $trabajadorId = session("rit_progreso_{$this->token}.trabajador_id");
        if (!$trabajadorId) {
            return;
        }

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')
            ->where('id', $trabajadorId)
            ->where('empresa_id', $this->empresa->id)
            ->first();

        if (!$trabajador) {
            session()->forget("rit_progreso_{$this->token}");
            return;
        }

        if ($trabajador->aceptoRitVigente()) {
            $this->etapa = 'ya_acepto';
            return;
        }

        if ($this->fase === 'publicacion' && $trabajador->confirmoPublicacionVigente()) {
            $this->etapa = 'ya_informado';
            return;
        }

        $this->trabajadorId = $trabajador->id;
        $this->trabajadorExistente = true;
        $this->tipoDocumento = $trabajador->tipo_documento;
        $this->numeroDocumento = $trabajador->numero_documento;
        $this->nombres = $trabajador->nombres;
        $this->apellidos = $trabajador->apellidos;
        $this->genero = $trabajador->genero;
        $this->email = (string) $trabajador->email;
        $this->telefono = (string) $trabajador->telefono;
        $this->direccion = (string) $trabajador->direccion;

        $this->prepararPresentacionRit();
    }

    /**
     * Ver restaurarProgresoDesdeSesion(). Solo tiene sentido guardar una vez
     * que existe un trabajador_id real (no hay nada que recordar antes de
     * eso) y solo en el flujo público real (con token).
     */
    private function guardarProgresoEnSesion(): void
    {
        if ($this->token === '' || !$this->trabajadorId) {
            return;
        }

        session()->put("rit_progreso_{$this->token}", ['trabajador_id' => $this->trabajadorId]);
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
    public function resolverRitActivo(): ?ReglamentoInterno
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

            if ($this->fase === 'publicacion' && $trabajador->confirmoPublicacionVigente()) {
                $this->etapa = 'ya_informado';
                return;
            }
        }

        $this->etapa = 'datos';
    }

    public function guardarDatos(): void
    {
        // Correo y teléfono OPCIONALES (pedido explícito de Andrés Sarmiento
        // en la reunión, 2026-09-28: "que sea opcional" - esta pantalla NO
        // es para levantar datos de contacto, es para socializar el
        // Reglamento; el dato oficial de notificación lo captura RRHH por
        // fuera). Si el trabajador SÍ escribe un correo, la confirmación
        // sigue siendo obligatoria (protege contra un typo en ese caso).
        $this->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'genero' => 'required|string',
            'cargo' => 'required|string',
            'cargoPersonalizado' => $this->cargo === '__otro__' ? 'required|string|max:255' : 'nullable|string|max:255',
            'email' => 'nullable|email',
            'emailConfirmacion' => $this->email !== '' ? 'required|same:email' : 'nullable',
            'telefono' => 'nullable|string|max:50',
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
        $this->guardarProgresoEnSesion();

        if ($this->fase === 'publicacion') {
            $this->prepararPresentacionRit();
            return;
        }

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

        $this->prepararPresentacionRit();
    }

    /**
     * Calcula todo lo que la pantalla 'presentacion_rit' necesita mostrar
     * (texto del RIT, temas, diff de cambios, capítulos de video). Se
     * extrajo de guardarFotoSimple() (2026-09-30) para que la Fase 1
     * (Publicación, que NO pasa por la pantalla de foto) también pueda
     * llegar a 'presentacion_rit' sin duplicar esta lógica.
     */
    private function prepararPresentacionRit(): void
    {
        $trabajadorObj = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->find($this->trabajadorId);
        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        $ultimaAceptacion = $trabajadorObj?->aceptacionesReglamentoInterno()
            ->latest('aceptado_en')
            ->first();

        $this->ritActivoTextoCompleto = (string) $ritActivo->texto_completo;
        $this->capitulosVideoDidactico = array_column($ritActivo->capitulosVideoDidactico(), 'titulo');
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

            // Bug real reportado por el usuario (2026-10-03, captura de
            // RENBEL): el redline salía siempre en blanco ("No se
            // detectaron diferencias") aunque sí hubo cambios. Causa raíz:
            // el RIT puede mutar IN-PLACE (Plan B quirúrgico, mismo id) sin
            // crear una fila nueva - re-consultar ReglamentoInterno por
            // $ultimaAceptacion->reglamento_interno_id trae el texto YA
            // actualizado (el mismo que $ritActivoTextoCompleto), así que
            // se comparaba el texto nuevo contra sí mismo. El snapshot
            // guardado en el momento exacto de esa aceptación
            // (AceptacionReglamentoInterno.texto_rit_snapshot, ver
            // AceptacionRitService::registrar()) es la única fuente
            // confiable del texto "de antes". Respaldo al texto actual del
            // RIT solo para filas viejas de antes de esa columna
            // (migracion 2026_09_28_095031) que puedan tener el snapshot
            // vacío.
            $textoAnterior = $ultimaAceptacion->texto_rit_snapshot
                ?: ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
                    ->find($ultimaAceptacion->reglamento_interno_id)
                    ?->texto_completo;

            $this->cambiosRit = app(\App\Services\RitDiffService::class)->compararDocumentos(
                (string) $textoAnterior,
                $this->ritActivoTextoCompleto
            );
        } else {
            $this->esPrimeraAceptacion = true;
        }

        $this->etapa = 'presentacion_rit';
    }

    /**
     * Se llama al salir de 'presentacion_rit'. Elige 5 temas al azar entre
     * los que tienen pregunta_vf generada para el RIT activo - si hay
     * menos de 3 usa los que haya, si hay 0 salta la etapa entera
     * (fail-open: un fallo de IA generando el quiz nunca debe bloquear la
     * aceptación real del reglamento).
     */
    public function iniciarQuiz(): void
    {
        if ($this->fase === 'publicacion') {
            $this->etapa = 'aceptacion';
            return;
        }

        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3

        // Auto-sanación (bug real reportado en vivo, demo 2026-10-05, RENBEL:
        // el quiz nunca traía selección múltiple) - normalmente
        // ReglamentoInternoObserver ya llama esto al guardar el RIT, pero
        // solo si el TEXTO cambió; un RIT cuyas preguntas se generaron antes
        // de que existiera tipo_pregunta nunca se regenera por su cuenta
        // porque su texto ya no vuelve a cambiar. Llamarlo aquí (barato si
        // ya está al día, ver el staleness check de tipo_pregunta dentro del
        // propio método) asegura que cualquier RIT viejo se autocorrija la
        // primera vez que un trabajador llegue al quiz, sin depender de que
        // alguien recuerde correr `rit:clasificar-temas --todos`.
        app(\App\Services\TemaClasificadorService::class)->asegurarPreguntasQuiz($ritActivo);

        $temasQuiz = $ritActivo->temasNormativos()
            ->activos()
            ->wherePivotNotNull('pregunta_vf')
            ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion']);

        if ($this->genero !== 'femenino') {
            // Bug real reportado por el usuario (2026-10-03): a un
            // trabajador hombre le salió la pregunta de quiz sobre permisos
            // de lactancia ("...para amamantar a tu hijo..."), redactada
            // siempre en segunda persona por la IA como si quien responde
            // fuera la madre (ver TemaClasificadorService::generarPreguntasQuiz()).
            // Ese tema no aplica a trabajadores que no son mujeres - se
            // excluye del banco de preguntas en vez de intentar que la IA
            // redacte neutro (no hay forma confiable de validar eso).
            $temasQuiz = $temasQuiz->reject(
                fn ($tema) => $tema->nombre === 'Protección a la mujer embarazada y lactancia'
            );
        }

        // Pedido de Andrés Sarmiento (reunión 2026-10-03): si el trabajador
        // ya había aceptado una versión anterior del RIT (es una
        // actualización, no la primera vez), el quiz solo debe preguntar
        // sobre lo que CAMBIÓ - no todo el reglamento de nuevo. $cambiosRit
        // ya lo calculó prepararPresentacionRit() contra el snapshot real de
        // la última aceptación. Fail-open: si la IA no identifica ningún
        // tema afectado, se usan todos (mejor preguntar de más que saltarse
        // la evidencia de comprensión).
        if (!$this->esPrimeraAceptacion && !empty($this->cambiosRit) && $temasQuiz->isNotEmpty()) {
            $idsAfectados = app(\App\Services\TemaClasificadorService::class)->identificarTemasDelCambio($this->cambiosRit, $temasQuiz);
            if (!empty($idsAfectados)) {
                $temasQuiz = $temasQuiz->whereIn('id', $idsAfectados);
            }
        }

        $banco = $temasQuiz
            ->map(fn ($tema) => [
                'pregunta' => $tema->pivot->pregunta_vf,
                'tipo' => $tema->pivot->tipo_pregunta ?: 'vf',
                'respuesta_correcta' => (bool) $tema->pivot->respuesta_correcta,
                'opciones' => $tema->pivot->opciones,
                'respuesta_correcta_indice' => $tema->pivot->respuesta_correcta_indice,
                'explicacion' => $tema->pivot->resumen_simple ?: $tema->descripcion,
            ]);

        $this->quizPreguntas = $this->seleccionarPreguntasQuiz($banco);

        $this->quizIndiceActual = 0;
        $this->quizRespuestaIncorrecta = false;

        // Evidencia jurídica: arranca vacío el registro de intentos de cada
        // pregunta - responderQuiz()/responderQuizMultiple() lo va llenando.
        $this->quizRespuestas = array_map(
            fn (array $p) => [
                'pregunta' => $p['pregunta'],
                'tipo' => $p['tipo'],
                'respuesta_correcta' => $p['tipo'] === 'multiple' ? $p['respuesta_correcta_indice'] : $p['respuesta_correcta'],
                'intentos' => [],
            ],
            $this->quizPreguntas
        );

        $this->etapa = empty($this->quizPreguntas) ? 'foto_aceptacion' : 'quiz';
    }

    /**
     * Pedido explícito del usuario (2026-10-05): el quiz SIEMPRE debe traer
     * exactamente 3 preguntas de selección múltiple y 2 de Sí/No - no debe
     * quedar a lo que la IA haya elegido libremente por tema al generarlas
     * (ver TemaClasificadorService::generarPreguntasQuiz()). Pero el ORDEN
     * final sí debe ser aleatorio (no mostrar siempre "3 múltiple y luego 2
     * sí/no" como bloque predecible).
     *
     * Fail-open: si el banco no tiene suficientes de un tipo (RIT con pocos
     * temas, o la IA generó poca variedad), se completa con el tipo que
     * sobre en vez de bloquear el quiz - mejor un quiz de 5 preguntas con
     * una mezcla distinta a dejar al trabajador sin quiz.
     */
    private function seleccionarPreguntasQuiz(\Illuminate\Support\Collection $banco): array
    {
        $multiples = $banco->filter(fn (array $p) => $p['tipo'] === 'multiple')->shuffle()->values();
        $vf = $banco->filter(fn (array $p) => $p['tipo'] !== 'multiple')->shuffle()->values();

        $seleccionMultiple = $multiples->take(3);
        $seleccionVf = $vf->take(2);

        $faltanMultiple = 3 - $seleccionMultiple->count();
        if ($faltanMultiple > 0) {
            $seleccionVf = $seleccionVf->merge($vf->slice($seleccionVf->count())->take($faltanMultiple));
        }

        $faltanVf = 2 - $seleccionVf->count();
        if ($faltanVf > 0) {
            $seleccionMultiple = $seleccionMultiple->merge($multiples->slice($seleccionMultiple->count())->take($faltanVf));
        }

        return $seleccionMultiple->merge($seleccionVf)->shuffle()->values()->all();
    }

    /**
     * Sin límite de intentos (decisión explícita del spec): si falla,
     * queda en la MISMA pregunta con quizRespuestaIncorrecta=true (la vista
     * muestra la explicación) hasta que marque la correcta. Cada intento
     * (fallido o exitoso) queda registrado en $quizRespuestas con su
     * timestamp - evidencia jurídica de que el trabajador realmente
     * presentó el quiz, no solo que lo "saltó".
     */
    public function responderQuiz(bool $respuesta): void
    {
        $preguntaActual = $this->quizPreguntas[$this->quizIndiceActual] ?? null;
        if (!$preguntaActual) {
            return;
        }

        $this->registrarIntentoQuiz($respuesta, $respuesta === $preguntaActual['respuesta_correcta']);
    }

    /**
     * Paralelo de responderQuiz() para preguntas tipo='multiple' (pedido de
     * Andrés Sarmiento, 2026-10-03: "que no sean 3 preguntas sino 5, de
     * selección múltiple o verdadero/falso"). $indiceElegido es la posición
     * de la opción que el trabajador marcó (0-3).
     */
    public function responderQuizMultiple(int $indiceElegido): void
    {
        $preguntaActual = $this->quizPreguntas[$this->quizIndiceActual] ?? null;
        if (!$preguntaActual) {
            return;
        }

        $this->registrarIntentoQuiz($indiceElegido, $indiceElegido === $preguntaActual['respuesta_correcta_indice']);
    }

    /**
     * Sin límite de intentos (decisión explícita del spec): si falla, queda
     * en la MISMA pregunta con quizRespuestaIncorrecta=true (la vista
     * muestra la explicación) hasta que marque la correcta. Cada intento
     * (fallido o exitoso) queda registrado en $quizRespuestas con su
     * timestamp - evidencia jurídica de que el trabajador realmente
     * presentó el quiz, no solo que lo "saltó". Compartido entre
     * responderQuiz() (V/F) y responderQuizMultiple() (selección múltiple).
     */
    /**
     * Fallar la MISMA pregunta esta cantidad de veces seguidas se interpreta
     * como una señal de "no comprendió" (pedido de Andrés Sarmiento,
     * 2026-10-03), igual que marcarlo manualmente - ver marcarNoComprendio().
     */
    private const UMBRAL_FALLOS_QUIZ_NO_COMPRENDIO = 3;

    private function registrarIntentoQuiz(bool|int $respuestaDada, bool $correcta): void
    {
        $this->quizRespuestas[$this->quizIndiceActual]['intentos'][] = [
            'respuesta_dada' => $respuestaDada,
            'correcta' => $correcta,
            'respondido_en' => now()->toISOString(),
        ];

        if (!$correcta) {
            $this->quizRespuestaIncorrecta = true;

            $fallosEnEstaPregunta = count(array_filter(
                $this->quizRespuestas[$this->quizIndiceActual]['intentos'],
                fn (array $i) => !$i['correcta']
            ));

            if ($fallosEnEstaPregunta === self::UMBRAL_FALLOS_QUIZ_NO_COMPRENDIO) {
                $this->marcarNoComprendio('fallo_quiz_repetido');
            }

            return;
        }

        $this->quizRespuestaIncorrecta = false;
        $this->quizIndiceActual++;

        if ($this->quizIndiceActual >= count($this->quizPreguntas)) {
            $this->etapa = 'foto_aceptacion';
        }
    }

    /**
     * Escalamiento a RRHH (pedido de Andrés Sarmiento, 2026-10-03): la
     * PRIMERA vez solo refuerza que ya se le mostró el material didáctico
     * (banner, no bloquea). La SEGUNDA vez bloquea el proceso y avisa a
     * RRHH por correo - de ahí en adelante la decisión es de ellos, no del
     * sistema. $origen es evidencia de qué lo disparó ('boton_manual' o
     * 'fallo_quiz_repetido').
     */
    public function marcarNoComprendio(string $origen): void
    {
        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        if (!$ritActivo || !$this->trabajadorId) {
            return;
        }

        $this->vecesNoComprendio++;

        \App\Models\RechazoComprensionRit::create([
            'trabajador_id' => $this->trabajadorId,
            'reglamento_interno_id' => $ritActivo->id,
            'texto_rit_hash' => hash('sha256', (string) $ritActivo->texto_completo),
            'origen' => $origen,
        ]);

        if ($this->vecesNoComprendio < 2) {
            return;
        }

        // Fail-open: si el correo a RRHH falla, el bloqueo del trabajador
        // igual debe quedar en pie - mismo criterio que el resto de correos
        // de este flujo (RitAceptado, RitPublicacionInformada).
        if ($this->empresa->email_contacto) {
            try {
                \Illuminate\Support\Facades\Mail::to($this->empresa->email_contacto)->send(
                    new \App\Mail\RitNoComprendidoAlertaRrhh(
                        trim("{$this->nombres} {$this->apellidos}"),
                        $this->numeroDocumento,
                        $this->empresa
                    )
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SocializacionRit: fallo al enviar alerta de no comprension a RRHH', [
                    'trabajador_id' => $this->trabajadorId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->etapa = 'no_comprendido_bloqueado';
    }

    /**
     * Pedido del usuario (2026-10-05): el aviso de 1er "no entendí" le decía
     * al trabajador que repasara el video/resumen/texto resaltado pero no
     * existía ningún botón para hacerlo - quedaba sin salida. Vuelve a
     * 'presentacion_rit' sin recalcular nada (toda la data ya sigue en
     * memoria del componente: $ritActivoTextoCompleto, $capitulosVideoDidactico,
     * $temasRit, $cambiosRit, $esPrimeraAceptacion). Se reinicia la
     * declaración de aceptación para que la vuelva a confirmar de forma
     * consciente tras repasar, en vez de arrastrar un check ya marcado.
     */
    public function volverARevisar(): void
    {
        $this->declaracionAceptada = false;
        $this->etapa = 'presentacion_rit';
    }

    /**
     * Selfie de ESTA aceptación puntual (evidencia jurídica: distinta de la
     * foto de referencia tomada en la etapa 'foto', que puede ser de una
     * visita anterior sin relación con este acto de aceptación). Reusa el
     * mismo servicio/criterio de calidad que validarFotoConIA(), pero NO
     * toca foto_referencia_path ni pasa por guardarFotoSimple() - solo
     * guarda el base64 en memoria, AceptacionRitService la persiste al
     * llamar aceptarReglamento().
     */
    public function validarFotoAceptacionConIA(string $fotoBase64): void
    {
        $this->validandoFotoAceptacion = true;
        $this->errorValidacionFotoAceptacion = '';

        $resultado = app(\App\Services\VerificacionFacialService::class)->validarCalidadFoto($fotoBase64);

        if (!$resultado['ok']) {
            $this->validandoFotoAceptacion = false;
            $this->errorValidacionFotoAceptacion = $resultado['motivo']
                ?? 'La foto no cumple los requisitos. Por favor intente de nuevo.';
            return;
        }

        $this->validandoFotoAceptacion = false;
        $this->fotoAceptacionBase64 = $fotoBase64;
        $this->etapa = 'aceptacion';
    }

    public function aceptarReglamento(): void
    {
        $this->validate([
            'declaracionAceptada' => 'accepted',
        ]);

        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->findOrFail($this->trabajadorId);

        app(\App\Services\AceptacionRitService::class)->registrar(
            $trabajador,
            $ritActivo,
            $this->quizRespuestas,
            $this->fotoAceptacionBase64 ?: null,
            request()->ip(),
            (string) request()->userAgent(),
        );

        app(\App\Services\LogroSocializacionRitService::class)->revisarYOtorgar($this->empresa);

        // Fail-open: si el correo falla (SMTP caido, direccion invalida
        // que paso la validacion basica, etc.) no debe bloquear el registro
        // ya guardado - el trabajador ya quedo aceptado en la BD.
        if ($this->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($this->email)->send(
                    new \App\Mail\RitAceptado(trim("{$this->nombres} {$this->apellidos}"), $this->empresa->razon_social, $this->empresa)
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

    /**
     * Paralelo de aceptarReglamento() para la Fase 1 (Publicación) - crea
     * evidencia LIGERA (PublicacionReglamentoInterno), nunca
     * AceptacionReglamentoInterno (esa es exclusiva de Fase 2).
     */
    public function confirmarPublicacion(): void
    {
        $this->validate([
            'declaracionAceptada' => 'accepted',
        ]);

        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->findOrFail($this->trabajadorId);

        \App\Models\PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $ritActivo->id,
            'texto_rit_hash' => hash('sha256', (string) $ritActivo->texto_completo),
            'confirmado_en' => now(),
            'ip' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        // Fail-open: mismo criterio que aceptarReglamento() - un fallo de
        // correo no debe bloquear el registro ya guardado en BD.
        if ($this->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($this->email)->send(
                    new \App\Mail\RitPublicacionInformada(trim("{$this->nombres} {$this->apellidos}"), $this->empresa->razon_social, $this->empresa)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SocializacionRit: fallo al enviar correo de publicacion', [
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
