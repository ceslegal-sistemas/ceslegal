<?php
$file = 'app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php';
$content = file_get_contents($file);

// Encontrar la sección problemática y eliminarla
$pattern = "/Forms::Components::Section::make\('Crear Trabajador'\)[\s\S]*?->createOptionModalHeading\('Crear Nuevo Trabajador'\),/";

// Reemplazar por vacío
$content = preg_replace($pattern, '', $content);

file_put_contents($file, $content);
echo "Arreglado\n";
