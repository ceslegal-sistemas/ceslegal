<?php

namespace App\Services;

use App\Models\Timeline;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TimelineService
{
    /**
     * Registra una acción en el timeline
     */
    public function registrar(
        string $procesoTipo,
        int $procesoId,
        string $accion,
        ?string $descripcion = null,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null,
        ?array $metadata = null,
        ?int $userId = null
    ): Timeline {
        return Timeline::create([
            'proceso_tipo' => $procesoTipo,
            'proceso_id' => $procesoId,
            'user_id' => $userId ?? Auth::id(),
            'accion' => $accion,
            'descripcion' => $descripcion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Registra la creación de un proceso
     */
    public function registrarCreacion(
        string $procesoTipo,
        int $procesoId,
        string $descripcion,
        ?array $metadata = null
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Creación',
            descripcion: $descripcion,
            estadoNuevo: 'creado',
            metadata: $metadata
        );
    }

    /**
     * Registra un cambio de estado
     */
    public function registrarCambioEstado(
        string $procesoTipo,
        int $procesoId,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?string $descripcion = null,
        ?array $metadata = null
    ): Timeline {
        // Generar descripción por defecto
        $descripcionDefault = $estadoAnterior
            ? "Estado cambiado de {$estadoAnterior} a {$estadoNuevo}"
            : "Estado inicial: {$estadoNuevo}";

        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Cambio de estado',
            descripcion: $descripcion ?? $descripcionDefault,
            estadoAnterior: $estadoAnterior,
            estadoNuevo: $estadoNuevo,
            metadata: $metadata
        );
    }

    /**
     * Registra una actualización de datos
     */
    public function registrarActualizacion(
        string $procesoTipo,
        int $procesoId,
        string $descripcion,
        ?array $cambios = null
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Actualización',
            descripcion: $descripcion,
            metadata: $cambios
        );
    }

    /**
     * Registra la generación de un documento
     */
    public function registrarDocumentoGenerado(
        string $procesoTipo,
        int $procesoId,
        string $tipoDocumento,
        string $nombreArchivo
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Documento generado',
            descripcion: "Se generó el documento: {$tipoDocumento}",
            metadata: [
                'tipo_documento' => $tipoDocumento,
                'nombre_archivo' => $nombreArchivo,
            ]
        );
    }

    /**
     * Registra una notificación enviada
     */
    public function registrarNotificacion(
        string $procesoTipo,
        int $procesoId,
        string $tipoNotificacion,
        string $destinatario
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Notificación enviada',
            descripcion: "Notificación de tipo {$tipoNotificacion} enviada a {$destinatario}",
            metadata: [
                'tipo' => $tipoNotificacion,
                'destinatario' => $destinatario,
            ]
        );
    }

    /**
     * Registra una asignación de abogado
     */
    public function registrarAsignacion(
        string $procesoTipo,
        int $procesoId,
        int $abogadoId,
        string $nombreAbogado
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Asignación de abogado',
            descripcion: "Proceso asignado a {$nombreAbogado}",
            metadata: [
                'abogado_id' => $abogadoId,
                'abogado_nombre' => $nombreAbogado,
            ]
        );
    }

    /**
     * Registra un comentario u observación
     */
    public function registrarComentario(
        string $procesoTipo,
        int $procesoId,
        string $comentario
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Comentario agregado',
            descripcion: $comentario
        );
    }

    /**
     * Registra el archivo de un proceso
     */
    public function registrarArchivo(
        string $procesoTipo,
        int $procesoId,
        string $motivo
    ): Timeline {
        return $this->registrar(
            procesoTipo: $procesoTipo,
            procesoId: $procesoId,
            accion: 'Proceso archivado',
            descripcion: "Proceso archivado. Motivo: {$motivo}",
            estadoNuevo: 'archivado',
            metadata: [
                'motivo' => $motivo,
            ]
        );
    }

    /**
     * Obtiene el timeline completo de un proceso
     */
    public function obtenerTimeline(string $procesoTipo, int $procesoId)
    {
        return Timeline::where('proceso_tipo', $procesoTipo)
            ->where('proceso_id', $procesoId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtiene las últimas acciones del sistema (para dashboard)
     */
    public function obtenerUltimasAcciones(int $limite = 10)
    {
        return Timeline::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtiene el historial de acciones de un usuario
     */
    public function obtenerAccionesPorUsuario(int $userId, int $limite = 50)
    {
        return Timeline::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }
}
