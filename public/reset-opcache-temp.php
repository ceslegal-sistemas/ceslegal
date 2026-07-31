<?php
// TEMPORAL - script de diagnóstico para forzar un reseteo de OPcache en producción
// (ver conversación de la sesión: el archivo ProcesoDisciplinarioResource.php ya
// estaba actualizado en disco/commit correcto, pero PHP-FPM seguía sirviendo el
// bytecode compilado viejo). BORRAR este archivo apenas se confirme que funcionó -
// es un endpoint sin autenticación, no debe quedar público de forma permanente.
if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo $ok ? 'OPcache reseteado correctamente.' : 'opcache_reset() devolvio false (revisar si opcache.restrict_api esta configurado).';
} else {
    echo 'La funcion opcache_reset() no existe en este servidor (OPcache no esta habilitado via API).';
}
