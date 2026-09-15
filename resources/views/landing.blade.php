<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="utf-8">

    <title>LUPE Legal - Gestión disciplinaria con respaldo jurídico</title>

    <meta name="description" content="Plataforma que construye, ejecuta y documenta todo tu proceso disciplinario laboral - RIT, descargos, sanciones y contratos - con IA anclada a la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo.">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LUPE Legal">
    <meta property="og:title" content="LUPE Legal - Gestión disciplinaria con respaldo jurídico">
    <meta property="og:description" content="Plataforma que construye, ejecuta y documenta todo tu proceso disciplinario laboral con IA anclada a la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo.">
    <meta property="og:image" content="{{ asset('images/lupe-og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="LUPE Legal - Gestión disciplinaria con respaldo jurídico">
    <meta name="twitter:description" content="Plataforma que construye, ejecuta y documenta todo tu proceso disciplinario laboral con IA anclada a la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo.">
    <meta name="twitter:image" content="{{ asset('images/lupe-og-image.png') }}">

    <link rel="icon" type="image/png" href="/images/lupe-favicon.png">
    <link rel="apple-touch-icon" href="/images/lupe-favicon.png"/>

    <link href="/landing-v2/css/plugins.css" media="all" rel="stylesheet" type="text/css">
    <link href="/landing-v2/css/style.css" media="all" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Raleway:300,400,500,600,700,800%7CMontserrat:400,500,600,700,800" rel="stylesheet" type="text/css">

    <style>
        /* LUPE Legal - reemplazo de las fotografías de stock del template por
           el degradado de marca (mismo lenguaje visual que .rit-hero del panel) -
           no hay fotografía real de oficina/equipo que mostrar honestamente. */
        .lupe-hero-bg {
            background: linear-gradient(150deg, #1a0f0c 0%, #241319 55%, #170d0a 100%) !important;
        }
        .lupe-hero-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 20%, rgba(225,29,72,.35), transparent 55%),
                        radial-gradient(circle at 85% 80%, rgba(201,168,76,.18), transparent 50%);
        }
        .color-switch, .hero-heading span, a.link-effect { color: #E11D48 !important; }
        .btn-lupe-cta {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .85rem 1.75rem; border-radius: 2rem;
            background: #E11D48; color: #fff !important; font-weight: 700;
            text-decoration: none; transition: background .15s, transform .15s;
            border: none; cursor: pointer;
        }
        .btn-lupe-cta:hover { background: #be123c; transform: translateY(-2px); }
        .btn-lupe-outline {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .8rem 1.7rem; border-radius: 2rem;
            background: transparent; color: #fff !important; font-weight: 700;
            text-decoration: none; border: 1px solid rgba(255,255,255,.4);
        }
        .bg-dark .btn-lupe-outline { color: #fff !important; }
        .bg-light .btn-lupe-outline { color: #1c1917 !important; border-color: rgba(0,0,0,.2); }
        .facts-counter-number { color: #E11D48; }
        .lupe-check-icon { width: 20px; height: 20px; flex-shrink: 0; color: #E11D48; }
        .lupe-service-icon { width: 40px; height: 40px; flex-shrink: 0; color: #E11D48; margin-bottom: 1rem; }
        .lupe-stat-item { text-align: center; padding: 1.5rem 1rem; }
        .lupe-stat-label { font-size: .8125rem; letter-spacing: .04em; text-transform: uppercase; opacity: .75; margin-top: .5rem; }
        .lupe-step-numb { color: #E11D48; font-weight: 800; font-size: 2.5rem; opacity: .35; line-height: 1; }
        .header-brand .logo img { height: 48px; width: auto; }
    </style>

</head>

<body>

    <!-- preloader start -->
    <div class="preloader-bg"></div>
    <div id="preloader">
        <div id="preloader-status">
            <div class="preloader-position loader"><span></span></div>
        </div>
    </div>
    <!-- preloader end -->

    <div class="border-top top-position"></div>
    <div class="border-left left-position"></div>
    <div class="border-right right-position"></div>
    <div class="border-bottom bottom-position"></div>

    <!-- navigation start -->
    <nav class="navbar navbar-fixed-top navbar-bg-switch">
        <div class="container">
            <div class="navbar-header fadeIn-element">
                <div class="logo header-brand" style="display:flex;align-items:center;">
                    <a class="navbar-brand logo" href="/">
                        <img alt="LUPE Legal" class="logo-light" src="/images/lupe-logo.png">
                        <img alt="LUPE Legal" class="logo-dark" src="/images/lupe-logo.png">
                    </a>
                </div>
            </div>
            <div class="main-navigation fadeIn-element">
                <div class="navbar-header">
                    <button aria-expanded="false" class="navbar-toggle collapsed" data-target="#navbar-collapse" data-toggle="collapse" type="button">
                        <span class="sr-only">Menú</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
                    </button>
                </div>
                <div class="collapse navbar-collapse" id="navbar-collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <li><a href="#home">Inicio</a></li>
                        <li><a href="#about">Qué es</a></li>
                        <li><a href="#services">Qué resuelve</a></li>
                        <li><a href="#como-funciona">Cómo funciona</a></li>
                        <li><a href="#contact">Contacto</a></li>
                        <li><a href="/admin/login">Ingresar</a></li>
                        <li><a href="/admin/register">Registrarse</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <!-- navigation end -->

    <!-- home start -->
    <div class="upper-page" id="home">
        <div class="dots"><div class="the-dots"></div></div>
        <div class="dots-reverse"><div class="the-dots"></div></div>

        <div class="hero-fullscreen">
            <div class="hero-fullscreen-FIX">
                <div class="hero-bg lupe-hero-bg" style="position:relative;">
                    <div style="position:relative;z-index:2;height:100%;display:flex;align-items:center;">
                        <div class="container">
                            <h2 class="hero-subheading hero-subheading-home fadeIn-element"><span>Respaldo jurídico real</span></h2>
                            <div class="divider-m"></div>
                            <h1 class="hero-heading hero-heading-home fadeIn-element">
                                Decisiones laborales con fundamento jur<span>í</span>dico real
                            </h1>
                            <div class="divider-m"></div>
                            <p style="color:rgba(255,255,255,.75);font-size:1.05rem;max-width:640px;line-height:1.7;" class="fadeIn-element">
                                Cada análisis, redacción y sanción anclado a la Constitución, la jurisprudencia y el
                                Código Sustantivo del Trabajo - no son opiniones de una IA, son documentos jurídicos reales.
                            </p>
                            <div class="divider-l"></div>
                            <div class="fadeIn-element" style="display:flex;gap:1rem;flex-wrap:wrap;">
                                <a href="/admin/register" class="btn-lupe-cta">Empezar ahora</a>
                                <a href="#services" class="btn-lupe-outline">Ver qué resuelve</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs><path id="gentle-wave-1" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" /></defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave-1" x="48" y="0" fill="rgba(255, 255, 255, 0.7)" />
                <use xlink:href="#gentle-wave-1" x="48" y="3" fill="rgba(255, 255, 255, 0.5)" />
                <use xlink:href="#gentle-wave-1" x="48" y="5" fill="rgba(255, 255, 255, 0.3)" />
                <use xlink:href="#gentle-wave-1" x="48" y="7" fill="#fff" />
            </g>
        </svg>
    </div>
    <!-- home end -->

    <div class="vertical-lines-wrapper"><div class="vertical-lines"></div></div>

    <!-- about (Qué es) start -->
    <section id="about" class="section-all bg-light">
        <div class="divider-xl"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-dark"><span>01.</span>Quiénes somos</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-dark">Qué es <span>LUPE Legal</span></h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="testimonial">
                        <div class="inner">
                            <div class="quote">
                                <blockquote class="quote-inner">
                                    El diferencial no es la IA: es que cada decisión está respaldada por documentos
                                    jurídicos reales - Código Sustantivo del Trabajo, Constitución y jurisprudencia -
                                    no por opiniones de un modelo de lenguaje.
                                </blockquote>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                <div class="col-md-6 col-all">
                    <h4><span class="color-switch">Nuestra</span> misión</h4>
                    <div class="divider-m"></div>
                    <div class="txt">
                        <p>Que ninguna empresa tenga que elegir entre actuar rápido y actuar bien - que cada sanción,
                        cada citación a descargos y cada reglamento interno quede blindado desde el primer borrador,
                        con el debido proceso garantizado.</p>
                    </div>
                    <div class="divider-l visible-mobile-devices"></div>
                </div>
                <div class="col-md-6 col-all">
                    <h4><span class="color-switch">Nuestra</span> visión</h4>
                    <div class="divider-m"></div>
                    <div class="txt">
                        <p>Ser la capa jurídica de confianza detrás de cada decisión disciplinaria y contractual en
                        Latinoamérica - donde la IA acelera el trabajo, pero la ley real (nunca una opinión) sigue
                        siendo quien decide qué es correcto.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- about end -->

    <!-- know-how (stats reales) start -->
    <section id="know-how" class="section-all bg-dark lupe-hero-bg" style="position:relative;">
        <div class="divider-xl"></div>
        <div class="container" style="position:relative;z-index:2;">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-home"><span>02.</span>Base documental</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-home">Anclado a la <span>ley real</span></h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                <div class="col-sm-6 col-md-3 lupe-stat-item">
                    <h3 class="facts-counter-number">1.472</h3>
                    <div class="lupe-stat-label" style="color:rgba(255,255,255,.7);">Artículos del CST y la Constitución indexados y citables</div>
                </div>
                <div class="col-sm-6 col-md-3 lupe-stat-item">
                    <h3 class="facts-counter-number">16</h3>
                    <div class="lupe-stat-label" style="color:rgba(255,255,255,.7);">Capítulos por Reglamento Interno generado con IA</div>
                </div>
                <div class="col-sm-6 col-md-3 lupe-stat-item">
                    <h3 class="facts-counter-number">8</h3>
                    <div class="lupe-stat-label" style="color:rgba(255,255,255,.7);">Motores de validación revisando cada sanción antes de emitirse</div>
                </div>
                <div class="col-sm-6 col-md-3 lupe-stat-item">
                    <h3 class="facts-counter-number">100%</h3>
                    <div class="lupe-stat-label" style="color:rgba(255,255,255,.7);">Trazabilidad - cada correo, documento y decisión con registro verificable</div>
                </div>
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- know-how end -->

    <!-- services (Qué resuelve) start -->
    <section id="services" class="section-all bg-light">
        <div class="divider-xl"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-dark"><span>03.</span>Qué resuelve</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-dark">Todo el <span>proceso</span>, en un solo lugar</h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                <div class="services-steps-wrapper">
                    <div class="col-md-4 col-all services-block">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        <h4 class="service-heading">Reglamento Interno (RIT)</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">1</div>
                        <p>Construye tu RIT completo con IA en minutos, audítalo contra la ley vigente, y recibe
                        sugerencias automáticas de actualización cada vez que sale normativa nueva que te aplica.</p>
                    </div>
                    <div class="divider-l visible-mobile-devices"></div>
                    <div class="col-md-4 col-all services-block pull-up">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                        <h4 class="service-heading">Procesos disciplinarios</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">2</div>
                        <p>Guía paso a paso desde el incidente hasta la sanción: clasificación de gravedad con IA,
                        citación a descargos, formulario dinámico de preguntas, y detección automática de casos
                        de riesgo legal especial que requieren manejo cuidadoso.</p>
                    </div>
                    <div class="divider-l visible-mobile-devices"></div>
                    <div class="col-md-4 col-all services-block pull-up">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        <h4 class="service-heading">Emitir sanción con respaldo</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">3</div>
                        <p>Antes de firmar, el sistema corre 8 validaciones de calidad y coherencia probatoria, verifica
                        la autoridad disciplinaria según tu propio RIT, y exige verificación facial de quien autoriza.</p>
                    </div>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                <div class="services-steps-wrapper">
                    <div class="col-md-4 col-all services-block">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <h4 class="service-heading">Documentos y notificaciones</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">4</div>
                        <p>Genera citaciones, actas y sanciones en PDF con el membrete de tu propia empresa, y
                        notifica desde tu propio correo corporativo, con seguimiento de apertura como constancia.</p>
                    </div>
                    <div class="divider-l visible-mobile-devices"></div>
                    <div class="col-md-4 col-all services-block pull-up">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        <h4 class="service-heading">Contratos y otrosíes</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">5</div>
                        <p>Genera contratos a término fijo, indefinido o por obra, y otrosíes de modificación
                        contractual - con alertas automáticas antes de que venza un contrato a término fijo.</p>
                    </div>
                    <div class="divider-l visible-mobile-devices"></div>
                    <div class="col-md-4 col-all services-block pull-up">
                        <svg class="lupe-service-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h4 class="service-heading">Asistente con IA en el panel</h4>
                        <div class="divider-m"></div>
                        <div class="service-number">6</div>
                        <p>Tus clientes preguntan por chat el estado de su RIT, sus procesos disciplinarios o sus
                        trabajadores registrados - la IA responde con datos reales de su propia empresa, nunca inventados.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- services end -->

    <!-- por qué elegirnos start -->
    <section id="por-que" class="section-all bg-dark">
        <div class="divider-xl"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-home"><span>04.</span>Diferenciales</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-home">Por qué <span>elegirnos</span></h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                @php
                    $diferenciales = [
                        ['Fundamento jurídico real', 'No son opiniones de IA: cada salida cita su fuente documental - Constitución, jurisprudencia y Código Sustantivo del Trabajo.'],
                        ['Debido proceso garantizado', 'Enfoque garantista que protege el proceso y blinda a la empresa frente a demandas y acciones de repetición.'],
                        ['Grupos protegidos', 'Verifica si el trabajador pertenece a un grupo especialmente protegido por la ley, porque la norma aplica distinto.'],
                        ['Opciones, no imposiciones', 'El sistema presenta las medidas posibles con sus riesgos legales; la empresa elige la más adecuada dentro del marco.'],
                        ['Auditoría de RIT', 'Revisa tu reglamento sección por sección y genera una versión mejorada conforme a la ley vigente.'],
                        ['Trazabilidad total', 'Cada correo, documento y decisión queda registrado y verificable, listo para soportar cualquier instancia.'],
                    ];
                @endphp
                @foreach($diferenciales as $i => $item)
                    <div class="col-md-6 col-all" style="margin-bottom:2rem;">
                        <div class="service-number" style="color:#E11D48;font-weight:800;font-size:1rem;">0{{ $i + 1 }}.</div>
                        <h4 class="service-heading" style="color:#fff;">{{ $item[0] }}</h4>
                        <p style="color:rgba(255,255,255,.65);">{{ $item[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- por qué elegirnos end -->

    <!-- cómo funciona start -->
    <section id="como-funciona" class="section-all bg-light">
        <div class="divider-xl"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-dark"><span>05.</span>Cómo funciona</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-dark">De la falta a la <span>sanción</span>, sin atajos</h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                @php
                    $pasos = [
                        ['1', 'Registra el incidente', 'Describe qué pasó - la IA clasifica la gravedad, revisa tu RIT y te alerta si el caso requiere manejo especial.'],
                        ['2', 'Cita a descargos', 'El trabajador responde un formulario dinámico; la IA hace seguimiento respetando su derecho de defensa.'],
                        ['3', 'Emite la sanción', 'Con 8 validaciones de calidad, verificación de autoridad según tu RIT, y verificación facial de quien autoriza.'],
                        ['4', 'Notifica y archiva', 'El documento sale con tu membrete, se notifica por tu propio correo, y queda trazado para siempre.'],
                    ];
                @endphp
                @foreach($pasos as $paso)
                    <div class="col-sm-6 col-md-3 col-all" style="margin-bottom:2rem;">
                        <div class="lupe-step-numb">{{ $paso[0] }}</div>
                        <div class="divider-m"></div>
                        <h4 class="service-heading">{{ $paso[1] }}</h4>
                        <p>{{ $paso[2] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- cómo funciona end -->

    <!-- faq start -->
    <section id="faq" class="section-all bg-dark">
        <div class="divider-xl"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="hero-subheading hero-subheading-home"><span>06.</span>Preguntas frecuentes</h2>
                    <div class="divider-m"></div>
                    <h2 class="hero-heading hero-heading-home">Antes de <span>empezar</span></h2>
                </div>
            </div>
            <div class="divider-l"></div>
            <div class="row">
                @php
                    $faqs = [
                        ['¿En qué se basa la IA para sus análisis?', 'La IA no razona desde su propio criterio. Consulta una base robusta de jurisprudencia y precedentes judiciales, la Constitución de Colombia y el Código Sustantivo del Trabajo, cargados previamente. Cada redacción y recomendación cita la fuente documental que la sustenta.'],
                        ['¿Reemplaza a un abogado?', 'No. Es una herramienta de apoyo que estructura el proceso disciplinario con respaldo jurídico y reduce riesgos. La decisión final siempre es de la empresa, que elige la medida más adecuada dentro del marco legal.'],
                        ['¿Funciona fuera de Colombia?', 'El mismo modelo aplica a Latinoamérica y España, que comparten tradición de derecho romano. Solo cambia la base documental (la jurisprudencia y normativa local); el flujo del proceso es el mismo.'],
                        ['¿Cómo se envían las notificaciones?', 'Las comunicaciones se envían desde el correo de tu propia empresa, con tu identidad y razón social, e incluyen seguimiento de apertura para acreditar la notificación.'],
                        ['¿Mis documentos están protegidos?', 'Los reglamentos y documentos generados por la IA se entregan como PDF cifrado con permisos de solo impresión, evitando su edición o copia no autorizada. Los documentos que tú mismo subes se conservan tal cual.'],
                    ];
                @endphp
                <div class="col-lg-12">
                    <div class="accordion">
                        <ul class="list-style-none accordion-list">
                            @foreach($faqs as $i => $faq)
                                <li class="accordion-item {{ $i === 0 ? 'active' : '' }}">
                                    <div class="row gy-0">
                                        <div class="col-md-4">
                                            <div class="accordion-counter-outer">
                                                <div class="accordion-counter">0{{ $i + 1 }}.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="accordion-toggle">
                                                <div class="accordion-heading">{{ $faq[0] }}</div>
                                                <div class="accordion-btn"><div class="btn btn-min crm mc"><div></div></div></div>
                                            </div>
                                            <div class="accordion-content article" @if($i === 0) style="display:block;" @endif>
                                                <p>{{ $faq[1] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- faq end -->

    <!-- contact/cta start -->
    <section id="contact" class="section-all lupe-hero-bg" style="position:relative;">
        <div class="divider-xl"></div>
        <div class="container" style="position:relative;z-index:2;text-align:center;">
            <h2 class="hero-subheading hero-subheading-home"><span>07.</span>Empecemos</h2>
            <div class="divider-m"></div>
            <h2 class="hero-heading hero-heading-home">Lleva tu gestión disciplinaria <span>al siguiente nivel</span></h2>
            <div class="divider-l"></div>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;">
                <a href="/admin/register" class="btn-lupe-cta">Registrarse</a>
                <a href="/admin/login" class="btn-lupe-outline">Ingresar</a>
            </div>
            <div class="divider-l"></div>
            <p style="color:rgba(255,255,255,.6);">
                ¿Prefieres escribirnos primero? <a class="link-effect" href="mailto:admin@ceslegal.co">admin@ceslegal.co</a>
            </p>
        </div>
        <div class="divider-xl"></div>
    </section>
    <!-- contact/cta end -->

    <!-- footer start -->
    <section id="footer" class="section-all bg-light">
        <div class="divider-l"></div>
        <div class="container-fluid">
            <div class="row footer-credits">
                <div class="col-lg-12">
                    <div class="social-icons">
                        <ul>
                            <li><a class="ion-earth" href="https://ceslegal.co" rel="noopener"><span>Sitio web</span></a></li>
                            <li><a class="ion-email" href="mailto:admin@ceslegal.co"><span>Correo</span></a></li>
                        </ul>
                    </div>
                    <div class="divider-l"></div>
                </div>
                <div class="col-lg-12">
                    <div class="copyright">&copy; {{ date('Y') }} - LUPE Legal</div>
                </div>
            </div>
        </div>
        <div class="divider-l"></div>
    </section>
    <!-- footer end -->

    <!-- to top arrow start -->
    <div class="scroll-up-btn"><i class="ion-ios-arrow-up"></i></div>
    <!-- to top arrow end -->

    <script src="/landing-v2/js/plugins.js"></script>
    <script src="/landing-v2/js/brandex-01.js"></script>

</body>
</html>
