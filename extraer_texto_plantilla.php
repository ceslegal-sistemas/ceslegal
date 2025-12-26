<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

echo "📄 EXTRACCIÓN DE TEXTO DE LA PLANTILLA\n";
echo str_repeat("=", 60) . "\n\n";

$templatePath = __DIR__ . '/FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx';

try {
    $phpWord = IOFactory::load($templatePath);

    echo "✅ Plantilla cargada\n\n";
    echo "🔍 Buscando patrones de variables...\n\n";

    $allText = '';

    foreach ($phpWord->getSections() as $section) {
        $elements = $section->getElements();

        foreach ($elements as $element) {
            $elementClass = get_class($element);

            if (method_exists($element, 'getElements')) {
                foreach ($element->getElements() as $childElement) {
                    if (method_exists($childElement, 'getText')) {
                        $text = $childElement->getText();
                        $allText .= $text . ' ';
                    } elseif (method_exists($childElement, 'getElements')) {
                        foreach ($childElement->getElements() as $grandChild) {
                            if (method_exists($grandChild, 'getText')) {
                                $text = $grandChild->getText();
                                $allText .= $text . ' ';
                            }
                        }
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $text = $element->getText();
                $allText .= $text . ' ';
            }
        }
    }

    echo "📝 Texto extraído (primeros 2000 caracteres):\n";
    echo str_repeat("-", 60) . "\n";
    echo substr($allText, 0, 2000);
    echo "\n" . str_repeat("-", 60) . "\n\n";

    // Buscar patrones de variables
    $patterns = [
        '${...}' => preg_match_all('/\$\{[A-Z_]+\}/', $allText, $matches1),
        '{{...}}' => preg_match_all('/\{\{[A-Z_]+\}\}/', $allText, $matches2),
        '[...]' => preg_match_all('/\[[A-Z_]+\]/', $allText, $matches3),
        '<<...>>' => preg_match_all('/<<[A-Z_]+>>/', $allText, $matches4),
        '«...»' => preg_match_all('/«[A-Z_]+»/', $allText, $matches5),
    ];

    echo "🔍 ANÁLISIS DE PATRONES DE VARIABLES:\n\n";

    foreach ($patterns as $pattern => $count) {
        if ($count > 0) {
            echo "✅ Encontrados $count con formato $pattern\n";

            // Mostrar las variables encontradas
            switch ($pattern) {
                case '${...}':
                    echo "   Variables: " . implode(', ', array_unique($matches1[0])) . "\n";
                    break;
                case '{{...}}':
                    echo "   Variables: " . implode(', ', array_unique($matches2[0])) . "\n";
                    break;
                case '[...]':
                    echo "   Variables: " . implode(', ', array_unique($matches3[0])) . "\n";
                    break;
                case '<<...>>':
                    echo "   Variables: " . implode(', ', array_unique($matches4[0])) . "\n";
                    break;
                case '«...»':
                    echo "   Variables: " . implode(', ', array_unique($matches5[0])) . "\n";
                    break;
            }
            echo "\n";
        } else {
            echo "❌ No encontrados con formato $pattern\n";
        }
    }

    echo "\n💡 RECOMENDACIÓN:\n";
    echo str_repeat("-", 60) . "\n";

    $found = false;
    foreach ($patterns as $pattern => $count) {
        if ($count > 0 && $pattern !== '${...}') {
            echo "⚠️ La plantilla usa formato $pattern pero PHPWord requiere \${VARIABLE}\n";
            echo "   Debes modificar la plantilla para usar el formato correcto.\n";
            $found = true;
            break;
        }
    }

    if (!$found && $patterns['${...}'] > 0) {
        echo "✅ La plantilla usa el formato correcto \${VARIABLE}\n";
        echo "   Las variables deberían reemplazarse correctamente.\n";
    } elseif (!$found) {
        echo "⚠️ No se encontraron variables reconocibles en la plantilla.\n";
        echo "   Asegúrate de agregar marcadores de posición con formato \${VARIABLE}\n";
        echo "\n";
        echo "   Ejemplo de variables que debes agregar:\n";
        echo "   - \${EMPRESA_NOMBRE}\n";
        echo "   - \${TRABAJADOR_NOMBRE}\n";
        echo "   - \${CODIGO_PROCESO}\n";
        echo "   - \${FECHA_DESCARGOS}\n";
        echo "   - \${HECHOS}\n";
        echo "   - \${ARTICULOS_LEGALES}\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
