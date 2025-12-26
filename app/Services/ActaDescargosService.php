<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ActaDescargosService
{
    protected PhpWord $phpWord;

    public function __construct()
    {
        $this->phpWord = new PhpWord();
    }

    /**
     * Genera el acta de descargos en formato DOCX
     */
    public function generarActaDescargos(DiligenciaDescargo $diligencia): array
    {
        try {
            $proceso = $diligencia->proceso;
            $trabajador = $proceso->trabajador;
            $empresa = $proceso->empresa;

            // Crear sección principal
            $section = $this->phpWord->addSection([
                'marginLeft' => 1440,   // 1 pulgada
                'marginRight' => 1440,
                'marginTop' => 1440,
                'marginBottom' => 1440,
            ]);

            // Título
            $this->agregarTitulo($section);

            // Encabezado con información de la diligencia
            $this->agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa);

            // Hechos del proceso
            $this->agregarHechos($section, $proceso);

            // Preguntas y respuestas
            $this->agregarPreguntasRespuestas($section, $diligencia);

            // Información adicional de la diligencia
            $this->agregarInformacionAdicional($section, $diligencia);

            // Cierre
            $this->agregarCierre($section, $diligencia);

            // Firmas
            $this->agregarFirmas($section, $trabajador);

            // Guardar el documento
            $filename = $this->guardarDocumento($proceso);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => storage_path('app/actas_descargos/' . $filename),
            ];

        } catch (\Exception $e) {
            Log::error('Error generando acta de descargos', [
                'diligencia_id' => $diligencia->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Agrega el título del documento
     */
    protected function agregarTitulo($section): void
    {
        $section->addText(
            'ACTA DE DESCARGOS',
            [
                'bold' => true,
                'size' => 14,
                'name' => 'Arial',
            ],
            [
                'alignment' => Jc::CENTER,
                'spaceAfter' => 240,
            ]
        );
    }

    /**
     * Agrega el encabezado con información de la diligencia
     */
    protected function agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa): void
    {
        // Obtener datos de ubicación y fecha
        $municipio = $empresa->ciudad ?? 'Puerto Boyacá';
        $departamento = $empresa->departamento ?? 'Boyacá';

        $fecha = $diligencia->fecha_diligencia ?? now();
        $fechaTexto = $this->convertirFechaATexto($fecha);
        $hora = $fecha->format('H:i A');

        $modalidad = match($proceso->modalidad_descargos) {
            'presencial' => 'desde las oficinas administrativas de ' . $empresa->razon_social,
            'virtual' => 'a través del software virtual de descargos',
            'telefonico' => 'vía telefónica',
            default => 'desde las oficinas administrativas de ' . $empresa->razon_social,
        };

        // Construir párrafo de apertura
        $esFemenino = $trabajador->genero === 'femenino';
        $textLines = [
            "En la ciudad de {$municipio}, {$departamento}, el {$fechaTexto}, siendo las {$hora}, {$modalidad}, ",
            "se reunieron por una parte el representante legal de {$empresa->razon_social} con NIT {$empresa->nit} ",
            "en representación del empleador y, por la otra {$trabajador->nombre_completo}, ",
            "identificad" . ($esFemenino ? 'a' : 'o') . " con {$trabajador->tipo_documento} N° {$trabajador->numero_documento}, ",
            "en su condición de trabajador" . ($esFemenino ? 'a' : '') . " para que rinda sus descargos ",
            "y dé sus explicaciones acerca de los siguientes hechos:"
        ];

        $section->addText(
            implode('', $textLines),
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
        );
    }

    /**
     * Agrega los hechos del proceso
     */
    protected function agregarHechos($section, $proceso): void
    {
        // Limpiar HTML de los hechos
        $hechos = strip_tags($proceso->hechos);

        $section->addText(
            $hechos,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 240]
        );
    }

    /**
     * Agrega las preguntas y respuestas
     */
    protected function agregarPreguntasRespuestas($section, $diligencia): void
    {
        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        foreach ($preguntas as $pregunta) {
            // Agregar pregunta
            $section->addText(
                'PREGUNTA: ' . $pregunta->pregunta,
                [
                    'bold' => true,
                    'name' => 'Arial',
                    'size' => 11,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 60,
                ]
            );

            // Agregar respuesta
            if ($pregunta->respuesta) {
                $section->addText(
                    'RESPUESTA: ' . $pregunta->respuesta->respuesta,
                    [
                        'name' => 'Arial',
                        'size' => 11,
                    ],
                    [
                        'alignment' => Jc::BOTH,
                        'spaceAfter' => 120,
                    ]
                );

                // Si hay archivos adjuntos, mencionarlos
                if ($pregunta->respuesta->archivos_adjuntos && count($pregunta->respuesta->archivos_adjuntos) > 0) {
                    $section->addText(
                        'Archivos adjuntos a esta respuesta:',
                        [
                            'italic' => true,
                            'name' => 'Arial',
                            'size' => 10,
                        ],
                        [
                            'alignment' => Jc::BOTH,
                            'spaceAfter' => 30,
                        ]
                    );

                    foreach ($pregunta->respuesta->archivos_adjuntos as $archivo) {
                        $section->addText(
                            '  • ' . ($archivo['nombre'] ?? 'Archivo adjunto'),
                            [
                                'italic' => true,
                                'name' => 'Arial',
                                'size' => 10,
                            ],
                            [
                                'alignment' => Jc::BOTH,
                                'spaceAfter' => 30,
                            ]
                        );
                    }
                }
            } else {
                $section->addText(
                    'RESPUESTA: [Sin respuesta]',
                    [
                        'name' => 'Arial',
                        'size' => 11,
                        'italic' => true,
                    ],
                    [
                        'alignment' => Jc::BOTH,
                        'spaceAfter' => 120,
                    ]
                );
            }
        }
    }

    /**
     * Agrega información adicional de la diligencia (acompañante y pruebas)
     */
    protected function agregarInformacionAdicional($section, $diligencia): void
    {
        // Separador visual
        $section->addText(
            '',
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 120]
        );

        // Título de la sección
        $section->addText(
            'INFORMACIÓN ADICIONAL DE LA DILIGENCIA',
            [
                'bold' => true,
                'name' => 'Arial',
                'size' => 12,
            ],
            [
                'alignment' => Jc::CENTER,
                'spaceAfter' => 180,
            ]
        );

        // Información del acompañante (obtenida de las respuestas)
        $acompananteInfo = $this->obtenerInfoAcompanante($diligencia);

        if ($acompananteInfo['tiene_acompanante']) {
            $section->addText(
                'ACOMPAÑANTE DEL TRABAJADOR:',
                [
                    'bold' => true,
                    'name' => 'Arial',
                    'size' => 11,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 60,
                ]
            );

            $textoAcompanante = "Nombre: {$acompananteInfo['nombre']}";

            if (!empty($acompananteInfo['cargo'])) {
                $textoAcompanante .= "\nCargo/Relación: {$acompananteInfo['cargo']}";
            }

            $section->addText(
                $textoAcompanante,
                [
                    'name' => 'Arial',
                    'size' => 11,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 180,
                ]
            );
        } else {
            $section->addText(
                'ACOMPAÑANTE DEL TRABAJADOR: El trabajador no se hizo acompañar en esta diligencia.',
                [
                    'name' => 'Arial',
                    'size' => 11,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 180,
                ]
            );
        }

        // Información de pruebas aportadas
        $section->addText(
            'PRUEBAS APORTADAS:',
            [
                'bold' => true,
                'name' => 'Arial',
                'size' => 11,
            ],
            [
                'alignment' => Jc::BOTH,
                'spaceAfter' => 60,
            ]
        );

        if ($diligencia->pruebas_aportadas) {
            $textoPruebas = !empty($diligencia->descripcion_pruebas)
                ? $diligencia->descripcion_pruebas
                : 'El trabajador aportó pruebas durante la diligencia.';

            $section->addText(
                $textoPruebas,
                [
                    'name' => 'Arial',
                    'size' => 11,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 240,
                ]
            );
        } else {
            $section->addText(
                'El trabajador no aportó pruebas durante esta diligencia.',
                [
                    'name' => 'Arial',
                    'size' => 11,
                    'italic' => true,
                ],
                [
                    'alignment' => Jc::BOTH,
                    'spaceAfter' => 240,
                ]
            );
        }
    }

    /**
     * Agrega el cierre del acta
     */
    protected function agregarCierre($section, $diligencia): void
    {
        $fecha = $diligencia->fecha_diligencia ?? now();
        $fechaTexto = $this->convertirFechaATexto($fecha);
        $horaFin = $fecha->copy()->addMinutes(30)->format('H:i A'); // Asumimos 30 minutos de duración

        $textoCierre = "Se da por terminada la presente Diligencia a las {$horaFin} del {$fechaTexto}, " .
                      "anunciando al trabajador que se estudiará el asunto y que a la menor brevedad posible " .
                      "se le informará el resultado de la investigación de los hechos, y se suscribe por quienes " .
                      "en ella intervinieron:";

        $section->addText(
            $textoCierre,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 480]
        );
    }

    /**
     * Agrega las líneas de firma
     */
    protected function agregarFirmas($section, $trabajador): void
    {
        // Crear tabla para las firmas
        $table = $section->addTable([
            'borderSize' => 0,
            'width' => 100 * 50, // 100% width
        ]);

        // Fila con líneas de firma
        $table->addRow();
        $table->addCell(4500)->addText(
            '_____________________________',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText(''); // Espaciador
        $table->addCell(4500)->addText(
            '_____________________________',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );

        // Fila con nombres
        $table->addRow();
        $table->addCell(4500)->addText(
            'Representante del Empleador',
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText('');
        $table->addCell(4500)->addText(
            $trabajador->nombre_completo,
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );

        // Fila con cargos/documentos
        $table->addRow();
        $table->addCell(4500)->addText(
            'CES Legal',
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText('');
        $table->addCell(4500)->addText(
            "{$trabajador->tipo_documento} N° {$trabajador->numero_documento}",
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER]
        );
    }

    /**
     * Guarda el documento en el sistema de archivos
     */
    protected function guardarDocumento($proceso): string
    {
        $directory = storage_path('app/actas_descargos');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'acta_descargos_' . $proceso->codigo . '_' . time() . '.docx';
        $filepath = $directory . '/' . $filename;

        $objWriter = IOFactory::createWriter($this->phpWord, 'Word2007');
        $objWriter->save($filepath);

        return $filename;
    }

    /**
     * Convierte una fecha a texto en español
     */
    protected function convertirFechaATexto($fecha): string
    {
        $dia = $fecha->day;
        $mes = $fecha->month;
        $año = $fecha->year;

        $diaTexto = $this->numeroATexto($dia);
        $mesTexto = $this->obtenerMesTexto($mes);
        $añoTexto = $this->numeroATexto($año);

        return "{$diaTexto} ({$dia}) de {$mesTexto} del año {$añoTexto} ({$año})";
    }

    /**
     * Convierte un número a texto (simplificado)
     */
    protected function numeroATexto($numero): string
    {
        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce',
            15 => 'quince', 16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
        ];

        if ($numero < 10) {
            return $unidades[$numero];
        }

        if ($numero >= 10 && $numero < 20) {
            return $especiales[$numero] ?? '';
        }

        if ($numero >= 20 && $numero < 100) {
            $dec = intdiv($numero, 10);
            $uni = $numero % 10;
            return $decenas[$dec] . ($uni > 0 ? ' y ' . $unidades[$uni] : '');
        }

        if ($numero >= 100 && $numero < 1000) {
            // Implementación simplificada para centenas
            $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
            $cent = intdiv($numero, 100);
            $resto = $numero % 100;

            $texto = $centenas[$cent];
            if ($resto > 0) {
                $texto .= ' ' . $this->numeroATexto($resto);
            }
            return $texto;
        }

        if ($numero >= 1000 && $numero < 10000) {
            $mil = intdiv($numero, 1000);
            $resto = $numero % 1000;

            $texto = ($mil > 1 ? $this->numeroATexto($mil) . ' mil' : 'mil');
            if ($resto > 0) {
                $texto .= ' ' . $this->numeroATexto($resto);
            }
            return $texto;
        }

        // Para números más grandes, usar el número tal cual
        return (string) $numero;
    }

    /**
     * Obtiene el nombre del mes en texto
     */
    protected function obtenerMesTexto($mes): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];

        return $meses[$mes] ?? '';
    }

    /**
     * Determina si el trabajador es de género femenino basado en el nombre
     */
    protected function esGeneroFemenino($trabajador): bool
    {
        // Simplificado - podrías agregar un campo de género en el modelo
        $nombre = strtolower($trabajador->nombres);
        $terminacionesFemeninas = ['a', 'is', 'ez'];

        foreach ($terminacionesFemeninas as $terminacion) {
            if (str_ends_with($nombre, $terminacion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene la información del acompañante desde las respuestas a las preguntas
     */
    protected function obtenerInfoAcompanante($diligencia): array
    {
        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        $deseaAcompanante = false;
        $nombreAcompanante = '';
        $cargoAcompanante = '';

        foreach ($preguntas as $pregunta) {
            $preguntaTexto = strtolower($pregunta->pregunta);
            $respuesta = $pregunta->respuesta?->respuesta ?? '';

            // Primera pregunta: ¿Desea hacerse acompañar?
            if (str_contains($preguntaTexto, 'desea hacerse acompañar')) {
                $respuestaLower = strtolower(trim($respuesta));
                $deseaAcompanante = str_contains($respuestaLower, 'sí') ||
                                   str_contains($respuestaLower, 'si') ||
                                   str_contains($respuestaLower, 'yes');
            }

            // Segunda pregunta: Nombre del acompañante
            if (str_contains($preguntaTexto, 'nombre completo de la persona que lo acompañará')) {
                $nombreAcompanante = trim($respuesta);
                // Si respondió "No aplica", ignorar
                if (strtolower($nombreAcompanante) === 'no aplica') {
                    $nombreAcompanante = '';
                }
            }

            // Tercera pregunta: Cargo/relación del acompañante
            if (str_contains($preguntaTexto, 'cargo o relación de la persona que lo acompañará')) {
                $cargoAcompanante = trim($respuesta);
                // Si respondió "No aplica", ignorar
                if (strtolower($cargoAcompanante) === 'no aplica') {
                    $cargoAcompanante = '';
                }
            }
        }

        return [
            'tiene_acompanante' => $deseaAcompanante && !empty($nombreAcompanante),
            'nombre' => $nombreAcompanante,
            'cargo' => $cargoAcompanante,
        ];
    }

    /**
     * Convierte el DOCX a PDF (opcional)
     */
    public function convertirAPdf($docxPath): ?string
    {
        try {
            // Requiere dompdf o similar
            $pdfPath = str_replace('.docx', '.pdf', $docxPath);

            // TODO: Implementar conversión a PDF
            // Por ahora solo devolvemos null

            return null;
        } catch (\Exception $e) {
            Log::error('Error convirtiendo a PDF', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
