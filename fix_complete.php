<?php
// Leer archivo original del mensaje 1
$originalFile = 'app/Filament/Admin/Resources/Proceso DisciplinarioResource.php.backup';
if (!file_exists($originalFile)) {
    $originalFile = 'app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php.backup';
}

// Buscar en archivos actuales
echo "Archivos .backup:\n";
foreach (glob('app/Filament/Admin/Resources/*.backup*') as $file) {
    echo "  - $file\n";
}
