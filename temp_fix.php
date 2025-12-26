<?php
// Script para arreglar el archivo
$file = 'app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php';
$lines = file($file);

// Eliminar líneas 206-449 (índices 205-448)
array_splice($lines, 205, 244);

// Guardar
file_put_contents($file, implode('', $lines));
echo "Archivo arreglado\n";
