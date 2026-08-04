{{--
    Dispara generarHechos() (la redacción jurídica con IA) en cuanto el cliente
    llega al paso "revision" del wizard - sin que tenga que hacer clic en nada.

    Mismo patrón ya probado en este wizard para la cámara de "Verificación"
    (ver resources/views/filament/components/webcam-autorizador.blade.php y la
    memoria filament-wizard-x-init-eager): el Wizard raíz de Filament renderiza
    TODOS los pasos en el DOM desde el primer paso (solo los oculta con CSS) y
    declara `step` como variable reactiva de Alpine en su propio x-data; este
    componente, sin redeclarar `step`, la lee/observa heredándola del ancestro.

    (Un primer intento puso este mismo x-init/x-data directo en
    ->extraAttributes() de la Section "Descripción jurídica" - no disparó. La
    Section de Filament envuelve su contenido en marcado adicional propio (su
    propio x-data de colapso, etc.) que aparentemente no deja este x-init en el
    lugar/momento correcto del árbol. Un <div> propio, como el de la cámara, sí
    queda anidado donde se necesita.)
--}}
<div x-data="{ disparado: false }" x-init="
    const intentarGenerar = () => {
        if (disparado) return;
        if ((($wire.data ?? {}).hechos_ia ?? '') !== '') return;
        disparado = true;
        $wire.generarHechos();
    };
    if (step === 'revision') { intentarGenerar() }
    $watch('step', (valor) => { if (valor === 'revision') { intentarGenerar() } });
"></div>
