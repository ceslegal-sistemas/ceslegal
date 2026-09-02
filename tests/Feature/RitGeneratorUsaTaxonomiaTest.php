<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\FragmentoDocumento;
use App\Models\TemaNormativo;
use App\Services\BibliotecaLegalService;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: la taxonomía de 27 temas normativos (hoy
 * usada solo por RitActualizacionAutomaticaService, el flujo de
 * actualización de un RIT existente) también debe usarse al CONSTRUIR un
 * RIT desde cero, para priorizar contenido de Biblioteca Legal clasificado
 * en el tema de cada capítulo. Decisión explícita: boost, no filtro - un
 * fragmento que ya pasó el umbral de similitud nunca se pierde, solo puede
 * quedar después de los que sí coinciden con el tema.
 */
class RitGeneratorUsaTaxonomiaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cada nombre de tema usado en getCapitulos() debe existir tal cual en
     * temas_normativos - un typo aquí haría que la resolución nombre->ID
     * fallara en silencio (el capítulo quedaría sin boost, sin ningún
     * error visible).
     */
    public function test_todos_los_temas_declarados_en_los_capitulos_existen_en_la_taxonomia(): void
    {
        $nombresValidos = TemaNormativo::pluck('nombre')->all();

        $nombresUsados = collect(RITGeneratorService::getCapitulos())
            ->flatMap(fn(array $cap) => $cap['temas'] ?? [])
            ->unique();

        $this->assertNotEmpty($nombresUsados, 'Ningún capítulo declaró temas - la taxonomía no se estaría usando.');

        foreach ($nombresUsados as $nombre) {
            $this->assertContains(
                $nombre,
                $nombresValidos,
                "El tema '{$nombre}' declarado en un capítulo no existe en temas_normativos."
            );
        }
    }

    /**
     * Verifica el boost real en BibliotecaLegalService::buscarFragmentos():
     * un fragmento con MENOR similitud pero clasificado en el tema pedido
     * debe salir ANTES que uno con mayor similitud pero de un tema distinto.
     */
    public function test_buscar_fragmentos_prioriza_el_tema_sobre_el_score_puro(): void
    {
        Config::set('services.ia.gemini.api_key', 'fake-key');

        Http::fake([
            '*embedContent*' => Http::response([
                'embedding' => ['values' => [1.0, 0.0, 0.0]],
            ], 200),
        ]);

        $temaObjetivo = TemaNormativo::first();
        $temaOtro     = TemaNormativo::skip(1)->first();

        $docCoincide = DocumentoLegal::create([
            'titulo' => 'Documento del tema buscado', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);
        $docCoincide->temasNormativos()->attach($temaObjetivo->id);

        $docNoCoincide = DocumentoLegal::create([
            'titulo' => 'Documento de otro tema', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);
        $docNoCoincide->temasNormativos()->attach($temaOtro->id);

        // Mayor score (más parecido a [1,0,0]) pero de otro tema.
        FragmentoDocumento::create([
            'documento_legal_id' => $docNoCoincide->id, 'orden' => 1,
            'contenido' => 'Fragmento de otro tema, más parecido a la consulta.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        // Menor score pero del tema pedido.
        FragmentoDocumento::create([
            'documento_legal_id' => $docCoincide->id, 'orden' => 1,
            'contenido' => 'Fragmento del tema buscado, algo menos parecido.',
            'embedding' => [0.85, 0.53, 0.0],
        ]);

        $resultado = app(BibliotecaLegalService::class)->buscarFragmentos(
            'consulta de prueba',
            limite: 1,
            umbral: 0.30,
            temaIds: [$temaObjetivo->id],
        );

        $this->assertStringContainsString('Fragmento del tema buscado', $resultado);
        $this->assertStringNotContainsString('Fragmento de otro tema', $resultado);
    }

    /**
     * Sin temaIds, el comportamiento debe ser IDÉNTICO al de antes de este
     * cambio (otros llamadores, como el pipeline de descargos, no declaran
     * temas y no deben verse afectados).
     */
    public function test_sin_temaIds_se_comporta_como_antes_por_puro_score(): void
    {
        Config::set('services.ia.gemini.api_key', 'fake-key');

        Http::fake([
            '*embedContent*' => Http::response([
                'embedding' => ['values' => [1.0, 0.0, 0.0]],
            ], 200),
        ]);

        $doc = DocumentoLegal::create([
            'titulo' => 'Documento sin clasificar', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);
        FragmentoDocumento::create([
            'documento_legal_id' => $doc->id, 'orden' => 1,
            'contenido' => 'Fragmento más parecido a la consulta.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $resultado = app(BibliotecaLegalService::class)->buscarFragmentos(
            'consulta de prueba',
            limite: 1,
            umbral: 0.30,
        );

        $this->assertStringContainsString('Fragmento más parecido a la consulta', $resultado);
    }
}
