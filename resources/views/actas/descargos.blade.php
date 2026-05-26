<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 2.5cm 3cm; }
* { box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 11pt;
    color: #111111;
    margin: 0;
    padding: 0;
    line-height: 1.55;
}
.page {
    padding: 0;
}
.page-break {
    page-break-before: always;
}
.text-center { text-align: center; }
.text-justify { text-align: justify; }
.bold { font-weight: bold; }
.italic { font-style: italic; }
.small { font-size: 8.5pt; }
.smaller { font-size: 8pt; }
.mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 7.5pt; word-break: break-all; }
.section-title {
    font-size: 11pt;
    font-weight: bold;
    margin: 16pt 0 8pt;
}
.main-title {
    font-size: 13pt;
    font-weight: bold;
    text-align: center;
    margin: 0 0 4pt;
    text-transform: uppercase;
}
p { margin: 0 0 8pt; }
ol, ul { margin: 0 0 10pt; padding-left: 22pt; }
li { margin-bottom: 5pt; line-height: 1.55; }
table { border-collapse: collapse; width: 100%; }
.firma-table td { vertical-align: top; padding: 0; }
.firma-box {
    border: 1pt solid #aaaaaa;
    padding: 8pt 10pt;
    min-height: 90pt;
}
.photo-caption {
    font-size: 8.5pt;
    font-style: italic;
    text-align: center;
    color: #444444;
    margin-top: 4pt;
}
.q-label { font-weight: bold; font-size: 11pt; margin: 0 0 3pt; }
.a-label { margin: 0 0 2pt; }
.file-item { font-size: 9pt; font-style: italic; margin-left: 10pt; }
.footer-note {
    font-size: 9pt;
    font-style: italic;
    color: #444444;
    text-align: justify;
}
</style>
</head>
<body>
<div class="page">

{{-- TÍTULO --}}
<p class="main-title">Diligencia Administrativa de Apertura de Investigación Disciplinaria</p>
<p style="text-align:center; font-size:10pt; font-style:italic; margin:0 0 4pt;">(Antes denominada: Acta de Descargos)</p>
<p style="text-align:center; font-size:11pt; font-weight:bold; margin:0 0 20pt;">
    Proceso Disciplinario N.° {{ $proceso->codigo }}
</p>

{{-- APERTURA --}}
<p class="text-justify">{{ $apertura }}</p>

{{-- I. CONSTANCIA DE AUTENTICACIÓN DIGITAL --}}
<p class="section-title">I. CONSTANCIA DE AUTENTICACIÓN DIGITAL</p>

<p class="text-justify">
    La identidad {{ $g['del'] }} {{ $g['trabajador'] }} fue verificada mediante los siguientes
    mecanismos de seguridad de la plataforma CES Legal, conforme a lo previsto en la
    Ley 527 de 1999 (Comercio Electrónico) y el Decreto 2364 de 2012 (Firma Electrónica):
</p>

<ol>
    <li>
        <span class="bold">Código de verificación OTP (One-Time Password):</span>
        enviado por {{ $otpCanal }}{{ $otpEnviadoA ? ' al destinatario '.$otpEnviadoA : '' }}
        y verificado satisfactoriamente el {{ $otpVerif }}.
    </li>

    <li>
        <span class="bold">Declaración de participación voluntaria:</span>
        {{ ucfirst($g['el']) }} {{ $g['trabajador'] }} aceptó la declaración de responsabilidad
        y participación voluntaria en la plataforma el {{ $disclaimerEn }}.
    </li>

    <li>
        <span class="bold">Verificación facial al inicio de la diligencia:</span>
        fotografía capturada el {{ $fotoInicioEn }} mediante reconocimiento facial con
        inteligencia artificial, registrada en el sistema con fines de trazabilidad.
        @if($fotoInicioBase64)
        <div style="text-align:center; margin-top:6pt; margin-bottom:4pt;">
            <img src="{{ $fotoInicioBase64 }}" width="170" height="128">
            <p class="photo-caption">Fotografía de verificación — inicio de la diligencia | {{ $fotoInicioEn }}</p>
        </div>
        @endif
    </li>

    <li>
        <span class="bold">Verificación facial al cierre de la diligencia:</span>
        fotografía capturada el {{ $fotoFinEn }} mediante reconocimiento facial con
        inteligencia artificial, registrada en el sistema con fines de trazabilidad.
        @if($fotoFinBase64)
        <div style="text-align:center; margin-top:6pt; margin-bottom:4pt;">
            <img src="{{ $fotoFinBase64 }}" width="170" height="128">
            <p class="photo-caption">Fotografía de verificación — cierre de la diligencia | {{ $fotoFinEn }}</p>
        </div>
        @endif
    </li>

    <li><span class="bold">Dirección IP de acceso:</span> {{ $ipAcceso }}.</li>

    <li><span class="bold">Fecha y hora de ingreso a la plataforma:</span> {{ $primerAcceso }}.</li>
</ol>

{{-- II. HECHOS OBJETO DE LA INVESTIGACIÓN --}}
<p class="section-title">II. HECHOS OBJETO DE LA INVESTIGACIÓN</p>

<p class="text-justify">
    Se le informó {{ $g['al'] }} {{ $g['trabajador'] }} sobre los hechos que dieron origen
    al presente proceso disciplinario:
</p>

<p class="text-justify" style="margin-left:8pt;">{{ $hechos }}</p>

{{-- III. DESARROLLO DE LA DILIGENCIA --}}
<p class="section-title">III. DESARROLLO DE LA DILIGENCIA</p>

<p class="text-justify">
    A continuación se transcriben las preguntas formuladas {{ $g['al'] }} {{ $g['trabajador'] }}
    y las respuestas que {{ $g['este'] }} suministró de manera escrita a través de la
    plataforma CES Legal:
</p>

@if($preguntas->isEmpty())
<p class="italic text-justify">
    {{ ucfirst($g['el']) }} {{ $g['trabajador'] }} no respondió {{ $g['ninguno'] }} pregunta durante la diligencia.
</p>
@else
@foreach($preguntas as $idx => $pregunta)
<p style="margin-bottom:4pt;">
    <span class="q-label">PREGUNTA: {{ $pregunta->pregunta }}</span>
</p>
@if($pregunta->respuesta)
    <p class="a-label" style="margin-bottom:12pt;"><span class="bold">RESPUESTA:</span> {{ $pregunta->respuesta->respuesta ?? '[Sin respuesta]' }}</p>
    @if(!empty($pregunta->respuesta->archivos_adjuntos))
        <p class="smaller italic" style="margin:0 0 2pt;">Archivos adjuntos a esta respuesta:</p>
        @foreach($pregunta->respuesta->archivos_adjuntos as $archivo)
        <p class="file-item">• {{ $archivo['nombre'] ?? 'Archivo adjunto' }}</p>
        @endforeach
        <p style="margin-bottom:12pt;"></p>
    @endif
@else
    <p class="a-label italic" style="margin-bottom:12pt;"><span class="bold">RESPUESTA:</span> [Sin respuesta registrada]</p>
@endif
@endforeach
@endif

{{-- IV. INFORMACIÓN ADICIONAL --}}
<p class="section-title">IV. INFORMACIÓN ADICIONAL</p>

{{-- Acompañante --}}
@if($acompananteInfo['tiene_acompanante'])
<p><span class="bold">ACOMPAÑANTE {{ strtoupper($g['DEL']) }} {{ strtoupper($g['TRABAJADOR']) }}:</span></p>
<p style="margin-left:8pt; margin-bottom:4pt;">Nombre: {{ $acompananteInfo['nombre'] }}</p>
@if(!empty($acompananteInfo['cargo']))
<p style="margin-left:8pt; margin-bottom:10pt;">Cargo / Relación: {{ $acompananteInfo['cargo'] }}</p>
@endif
@else
<p style="margin-bottom:10pt;">
    <span class="bold">ACOMPAÑANTE {{ strtoupper($g['DEL']) }} {{ strtoupper($g['TRABAJADOR']) }}:</span>
    {{ ucfirst($g['el']) }} {{ $g['trabajador'] }} no se hizo {{ $g['acompanado'] }} en esta diligencia.
</p>
@endif

{{-- Pruebas --}}
<p><span class="bold">PRUEBAS APORTADAS:</span></p>
@if($tieneEvidencias)
    @if(!empty($descripcionPruebas))
        <p class="text-justify" style="margin-left:8pt;">{{ $descripcionPruebas }}</p>
    @else
        <p class="text-justify" style="margin-left:8pt;">
            {{ ucfirst($g['el']) }} {{ $g['trabajador'] }} adjuntó los siguientes archivos como prueba durante la diligencia:
        </p>
    @endif
    @if(!empty($archivosEvidencia))
    <ul>
        @foreach($archivosEvidencia as $archivo)
        <li class="small">
            {{ $archivo['nombre'] ?? 'Archivo' }}
            @if(isset($archivo['size'])) ({{ round($archivo['size'] / 1024, 1) }} KB)@endif
        </li>
        @endforeach
    </ul>
    @endif
@else
<p class="italic" style="margin-left:8pt;">
    {{ ucfirst($g['el']) }} {{ $g['trabajador'] }} no aportó pruebas adicionales durante esta diligencia.
</p>
@endif

{{-- V. CIERRE DE LA DILIGENCIA --}}
<p class="section-title">V. CIERRE DE LA DILIGENCIA</p>
<p class="text-justify" style="margin-bottom:24pt;">{{ $textoCierre }}</p>

{{-- FIRMAS --}}
<table class="firma-table" style="margin-top:6pt; table-layout:fixed;">
    <tr>
        <td style="width:48%; text-align:center; font-weight:bold; font-size:10pt; padding-bottom:6pt;">
            EMPLEADOR
        </td>
        <td style="width:4%;"></td>
        <td style="width:48%; text-align:center; font-weight:bold; font-size:10pt; padding-bottom:6pt;">
            {{ strtoupper($g['TRABAJADOR']) }}
        </td>
    </tr>
    <tr>
        {{-- Columna empleador: espacio para firma física --}}
        <td style="width:48%; vertical-align:top;">
            <div class="firma-box">
                <div style="height:60pt;"></div>
                <p style="text-align:center; margin:0 0 3pt;">_________________________________</p>
                <p style="text-align:center; font-size:9pt; margin:0 0 2pt;">Firma del Representante Legal</p>
                <p style="text-align:center; font-weight:bold; font-size:9.5pt; margin:0;">{{ $empresaNombre }}</p>
            </div>
        </td>
        <td style="width:4%;"></td>
        {{-- Columna trabajador: firma física --}}
        <td style="width:48%; vertical-align:top;">
            <div class="firma-box">
                <div style="height:60pt;"></div>
                <p style="text-align:center; margin:0 0 3pt;">_________________________________</p>
                <p style="text-align:center; font-size:9pt; margin:0 0 2pt;">{{ $nombreTrab }}</p>
                <p style="text-align:center; font-size:9pt; margin:0 0 2pt;">{{ $tipoDoc }} N.° {{ $numDoc }}</p>
                <p style="text-align:center; font-size:9pt; margin:0;">Cargo: {{ $cargo }}</p>
            </div>
        </td>
    </tr>
</table>

{{-- Nota aclaratoria --}}
<p style="margin-top:14pt;" class="footer-note">
    NOTA: La plataforma CES Legal actúa únicamente como proveedora del servicio tecnológico
    de gestión disciplinaria. La identidad {{ $g['del'] }} {{ $g['trabajador'] }} fue verificada
    mediante código OTP y doble reconocimiento facial con inteligencia artificial, lo que
    constituye su participación válida y consentida conforme a la Ley 527 de 1999 y el
    Decreto 2364 de 2012. Los registros de autenticación reposan en el expediente digital
    del proceso y están disponibles como prueba. CES Legal no asume responsabilidad alguna
    sobre las decisiones disciplinarias adoptadas por el empleador.
</p>

{{-- Pie de página --}}
<p style="text-align:center; font-size:8pt; font-style:italic; color:#666666; margin-top:10pt;">
    Generado el {{ $fechaGeneracion }} &nbsp;|&nbsp; Proceso N.° {{ $proceso->codigo }} &nbsp;|&nbsp; www.ceslegal.co
</p>

{{-- PÁGINA DE VERIFICACIÓN QR --}}
<div class="page-break"></div>

<p style="text-align:center; font-size:13pt; font-weight:bold; margin:0 0 6pt; text-transform:uppercase;">
    Verificación de Autenticidad del Documento
</p>
<p style="text-align:center; font-size:10pt; font-style:italic; margin:0 0 20pt;">
    Escanee el código QR o ingrese la URL en su navegador para verificar la autenticidad de este documento.
</p>

<table style="border-collapse:collapse; width:100%;">
    <tr>
        {{-- QR --}}
        <td style="width:150pt; text-align:center; vertical-align:middle; padding-right:20pt;">
            @if($qrBase64)
                <img src="{{ $qrBase64 }}" width="130" height="130">
            @else
                <p class="small italic" style="color:#888888;">QR no disponible</p>
            @endif
        </td>
        {{-- Datos de verificación --}}
        <td style="vertical-align:top; padding-top:4pt;">
            <p style="font-weight:bold; font-size:10pt; margin:0 0 8pt;">DATOS DE VERIFICACIÓN</p>

            <p class="small bold" style="margin:0 0 2pt; font-weight:bold;">URL de verificación:</p>
            <p class="mono" style="margin:0 0 10pt;">{{ $urlVerificacion }}</p>

            <p class="small bold" style="margin:0 0 2pt; font-weight:bold;">Token de verificación:</p>
            <p class="mono" style="margin:0 0 10pt;">{{ $token }}</p>

            <p class="small bold" style="margin:0 0 2pt; font-weight:bold;">Hash SHA-256 del documento:</p>
            <p class="mono" style="margin:0 0 10pt;">{{ $hash }}</p>

            <p class="small" style="margin:0 0 2pt;">Generado el: <span class="bold">{{ $fechaGeneracion }}</span></p>
            <p class="small" style="margin:0; font-weight:bold;">Proceso N.° {{ $proceso->codigo }}</p>
        </td>
    </tr>
</table>

<p style="margin-top:24pt;" class="footer-note">
    Documento con firma electrónica simple conforme a la Ley 527 de 1999 y el Decreto 2364 de 2012
    de la República de Colombia. La autenticidad de este documento puede ser verificada en cualquier
    momento mediante el código QR o la URL indicada. CES Legal actúa como proveedor tecnológico del
    servicio de gestión disciplinaria y no como parte en el proceso disciplinario.
</p>
<p style="text-align:center; font-size:8pt; color:#888888; margin-top:8pt;">
    CES Legal · www.ceslegal.co · Plataforma de Gestión Disciplinaria Laboral
</p>

</div>
</body>
</html>
