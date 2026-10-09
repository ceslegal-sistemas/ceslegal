<?php

namespace Tests\Unit;

use App\Services\EquivalenteFuncionalService;
use PHPUnit\Framework\TestCase;

class EquivalenteFuncionalServiceTest extends TestCase
{
    public function test_resumen_quiz_cuenta_aciertos_por_el_ultimo_intento(): void
    {
        $quiz = [
            ['pregunta' => 'a', 'intentos' => [['correcta' => false], ['correcta' => true]]],
            ['pregunta' => 'b', 'intentos' => [['correcta' => true]]],
            ['pregunta' => 'c', 'intentos' => [['correcta' => true], ['correcta' => false]]],
        ];

        $this->assertSame('2/3 correctas, 5 intentos', EquivalenteFuncionalService::resumenQuiz($quiz));
    }

    public function test_resumen_quiz_sin_datos(): void
    {
        $this->assertSame('Sin quiz', EquivalenteFuncionalService::resumenQuiz(null));
        $this->assertSame('Sin quiz', EquivalenteFuncionalService::resumenQuiz([]));
    }
}
