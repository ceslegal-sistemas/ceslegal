<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

echo "🔍 VERIFICACIÓN DE PLANTILLA WORD\n";
echo str_repeat("=", 60) . "\n\n";

// Ruta de la plantilla
$templatePath = __DIR__ . '/FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx';

if (!file_exists($templatePath)) {
    echo "❌ ERROR: No se encontró la plantilla en: $templatePath\n";
    exit(1);
}

echo "✅ Plantilla encontrada\n";
echo "📄 Ruta: $templatePath\n";
echo "📦 Tamaño: " . number_format(filesize($templatePath) / 1024, 2) . " KB\n\n";

try {
    echo "🔍 Intentando leer la plantilla con TemplateProcessor...\n";
    $templateProcessor = new TemplateProcessor($templatePath);

    echo "✅ Plantilla cargada correctamente\n\n";

    // Intentar obtener las variables
    echo "🔍 Buscando variables en el documento...\n";
    echo "Nota: PHPWord busca variables con formato \${VARIABLE}\n\n";

    // Intentar reemplazar una variable de prueba
    try {
        $templateProcessor->setValue('EMPRESA_NOMBRE', 'EMPRESA DE PRUEBA');
        $templateProcessor->setValue('TRABAJADOR_NOMBRE', 'TRABAJADOR DE PRUEBA');
        $templateProcessor->setValue('CODIGO_PROCESO', 'TEST-001');

        echo "✅ Variables de prueba establecidas\n\n";

        // Guardar el documento de prueba
        $testPath = __DIR__ . '/storage/app/temp/test_plantilla_' . time() . '.docx';

        // Crear directorio si no existe
        if (!file_exists(dirname($testPath))) {
            mkdir(dirname($testPath), 0755, true);
        }

        $templateProcessor->saveAs($testPath);

        echo "✅ Documento de prueba generado\n";
        echo "📄 Ruta: $testPath\n";
        echo "📦 Tamaño: " . number_format(filesize($testPath) / 1024, 2) . " KB\n\n";

        echo "💡 Abre este archivo para verificar si las variables se reemplazaron:\n";
        echo "   $testPath\n\n";

        echo "🔍 Si las variables NO se reemplazaron, la plantilla necesita tener:\n";
        echo "   - Variables con el formato: \${NOMBRE_VARIABLE}\n";
        echo "   - Ejemplo: \${EMPRESA_NOMBRE}\n";
        echo "   - NO usar: {{EMPRESA_NOMBRE}} o [EMPRESA_NOMBRE]\n\n";

    } catch (Exception $e) {
        echo "❌ ERROR al intentar reemplazar variables: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR al cargar la plantilla: " . $e->getMessage() . "\n";
    echo "\nPosibles causas:\n";
    echo "1. El archivo no es un .docx válido\n";
    echo "2. El archivo está corrupto\n";
    echo "3. El archivo está protegido\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Verificación completada\n";
