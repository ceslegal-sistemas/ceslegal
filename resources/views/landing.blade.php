<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <title>
            LUPE Legal - Gestión disciplinaria con respaldo jurídico
        </title>
        <meta content="Plataforma que construye, ejecuta y documenta todo tu proceso disciplinario laboral con IA anclada a la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo." name="description">
        <meta content="LUPE Legal" name="author">
        <meta content="reglamento interno, procesos disciplinarios, descargos, sanciones laborales, IA legal" name="keywords">
        <meta content="width=device-width, initial-scale=1, maximum-scale=1" name="viewport">
        <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">
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
        <!-- style start -->
        <link href="/landing-v2/css/plugins.css" media="all" rel="stylesheet" type="text/css">
        <link href="/landing-v2/css/style.css" media="all" rel="stylesheet" type="text/css">
        <!-- style end -->
        <!-- google fonts start -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,200,300,400,500,600,700,800,900%7CMontserrat:400,400i,500,500i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet" type=
            "text/css">
        <!-- google fonts end -->
        <!--
            La plantilla ancla el espaciado del párrafo junto al número
            grande (.service-number) al ID "#services" en vez de a la
            clase reutilizable - por eso el mismo bloque numerado, al
            reusarlo en la sección "Cómo funciona" (id distinto), quedaba
            con el texto tapado por el número. Se replica exactamente la
            misma regla (mismos valores, mismos breakpoints) para el
            nuevo ID, sin tocar style.css.
        -->
        <style>
            #como-funciona p { position: relative; padding-left: 135px; text-align: left; margin: -7px 0; }
            @media only screen and (max-width: 880px) { #como-funciona p { padding-left: 110px; } }
            @media only screen and (max-width: 768px) { #como-funciona p { padding-left: 90px; } }
            /* El footer original de la plantilla es minimalista a propósito
               (texto de 9-10px), pero al quitar dirección/teléfono/mapa
               (datos que no teníamos reales) quedó con muy poco peso visual.
               Se sube tamaño/espaciado del texto y se separa del logo con
               una línea sutil, sin tocar el CSS base de la plantilla. */
            #footer .social-icons { font-size: 13px; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(0,0,0,.08); display: inline-block; }
            #footer .social-icons li span { padding: 0 14px; }
            #footer .copyright { font-size: 11px; margin-top: .5rem !important; }
            /* Bug real (2026-09-15): el circulo decorativo ".timeline-image"
               (contador de 1.472 articulos) esta pensado para superponerse
               sobre la seccion siguiente con un offset fijo (top:200px en
               escritorio, top:183px en movil - ver style.css) - en escritorio
               hay suficiente aire antes del titulo "Los Servicios", pero en
               movil el mismo offset tapaba el encabezado "02. Que resuelve /
               Los Servicios". Se reduce el offset solo en movil, sin tocar
               style.css. */
            @media only screen and (max-width: 880px) {
                .timeline-image { top: 90px !important; }
            }
            @media only screen and (max-width: 480px) {
                .timeline-image { top: 60px !important; }
            }
        </style>
    </head>
    <body>
        <!-- preloader start -->
        <div class="preloader-bg"></div>
        <div id="preloader">
            <div id="preloader-status">
                <div class="preloader-position loader">
                    <span></span>
                </div>
            </div>
        </div>
        <!-- preloader end -->
        <!-- border top start -->
        <div class="border-top top-position"></div>
        <!-- border top end -->
        <!-- border left start -->
        <div class="border-left left-position"></div>
        <!-- border left end -->
        <!-- border right start -->
        <div class="border-right right-position"></div>
        <!-- border right end -->
        <!-- border bottom start -->
        <div class="border-bottom bottom-position"></div>
        <!-- border bottom end -->
        <!-- navigation start -->
        <nav class="navbar navbar-fixed-top navbar-bg-switch">
            <!-- container start -->
            <div class="container">
                <div class="navbar-header fadeIn-element">
                    <!-- logo start -->
                    <div class="logo">
                        <a class="navbar-brand logo" href="/">
                            <!-- logo light start -->
                            <img alt="LUPE Legal" class="logo-light" src="/landing-v2/img/logo-light.png?v=3">
                            <!-- logo light end -->
                            <!-- logo dark start -->
                            <img alt="LUPE Legal" class="logo-dark" src="/landing-v2/img/logo-dark.png?v=3">
                            <!-- logo dark end -->
                        </a>
                    </div>
                    <!-- logo end -->
                </div>
                <!-- main navigation start -->
                <div class="main-navigation fadeIn-element">
                    <div class="navbar-header">
                        <button aria-expanded="false" class="navbar-toggle collapsed" data-target="#navbar-collapse" data-toggle="collapse" type="button"><span class="sr-only">Toggle
                        navigation</span> <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span></button>
                    </div>
                    <div class="collapse navbar-collapse" id="navbar-collapse">
                        <!-- menu start -->
                        <ul class="nav navbar-nav navbar-right">
                            <li>
                                <a href="#home">Inicio</a>
                            </li>
                            <li>
                                <a href="#about">Qué es</a>
                            </li>
                            <li>
                                <a href="#services">Servicios</a>
                            </li>
                            <li>
                                <a href="#diferenciales">Diferenciales</a>
                            </li>
                            <li>
                                <a href="#como-funciona">Cómo funciona</a>
                            </li>
                            <li>
                                <a href="#contact">Contacto</a>
                            </li>
                            <li>
                                <a href="/admin/login">Ingresar</a>
                            </li>
                            <li>
                                <a href="/admin/register">Registrarse</a>
                            </li>
                        </ul>
                        <!-- menu end -->
                    </div>
                </div>
                <!-- main navigation end -->
            </div>
            <!-- container end -->
        </nav>
        <!-- navigation end -->
        <!-- home start -->
        <div class="upper-page" id="home">
            <!-- dots start -->
            <div class="dots">
                <div class="the-dots"></div>
            </div>
            <div class="dots-reverse">
                <div class="the-dots"></div>
            </div>
            <!-- dots end -->
            <!-- hero bg start -->
            <div class="hero-fullscreen">
                <div class="hero-fullscreen-FIX">
                    <div class="hero-bg">
                        <!-- hero slider wrapper start -->
                        <div class="swiper-container-wrapper">
                            <!-- swiper container start -->
                            <div class="swiper-container">
                                <!-- swiper wrapper start -->
                                <div class="swiper-wrapper">
                                    <!-- swiper slider item start -->
                                    <div class="swiper-slide">
                                        <div class="swiper-slide-inner" data-swiper-parallax="50%">
                                            <!-- swiper slider item IMG start -->
                                            <div class="swiper-slide-inner-bg bg-img-1">
                                            </div>
                                            <!-- swiper slider item IMG end -->
                                            <!-- overlay start -->
                                            <div class="overlay overlay-dark-75"></div>
                                            <!-- overlay end -->
                                            <!-- swiper slider item txt start -->
                                            <div class="swiper-slide-inner-txt">
                                                <!-- section subtitle start -->
                                                <a href="#services">
                                                    <h2 class="hero-subheading hero-subheading-home fadeIn-element">
                                                        <span>Respaldo jurídico real</span>
                                                    </h2>
                                                </a>
                                                <!-- section subtitle end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- section title start -->
                                                <h1 class="hero-heading hero-heading-home fadeIn-element">
                                                    Decisiones laborales con fundamento jur<span>í</span>dico real.
                                                </h1>
                                                <!-- section title end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- button start -->
                                                <div class="more-wraper-center more-wraper-center-home fadeIn-element">
                                                    <a href="/admin/register">
                                                        <div class="more-button-bg-center more-button-circle"></div>
                                                        <div class="more-button-txt-center">
                                                            <span>Empezar ahora</span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <!-- button end -->
                                            </div>
                                            <!-- swiper slider item txt end -->
                                        </div>
                                    </div>
                                    <!-- swiper slider item end -->
                                    <!-- swiper slider item start -->
                                    <div class="swiper-slide">
                                        <div class="swiper-slide-inner" data-swiper-parallax="50%">
                                            <!-- swiper slider item IMG start -->
                                            <div class="swiper-slide-inner-bg bg-img-2">
                                            </div>
                                            <!-- swiper slider item IMG end -->
                                            <!-- overlay start -->
                                            <div class="overlay overlay-dark-75"></div>
                                            <!-- overlay end -->
                                            <!-- swiper slider item txt start -->
                                            <div class="swiper-slide-inner-txt">
                                                <!-- section subtitle start -->
                                                <a href="#services">
                                                    <h2 class="hero-subheading hero-subheading-home fadeIn-element">
                                                        <span>Reglamento Interno con IA</span>
                                                    </h2>
                                                </a>
                                                <!-- section subtitle end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- section title start -->
                                                <h1 class="hero-heading hero-heading-home fadeIn-element">
                                                    Su RIT completo, list<span>o</span> en minutos.
                                                </h1>
                                                <!-- section title end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- button start -->
                                                <div class="more-wraper-center more-wraper-center-home fadeIn-element">
                                                    <a href="/admin/register">
                                                        <div class="more-button-bg-center more-button-circle"></div>
                                                        <div class="more-button-txt-center">
                                                            <span>Empezar ahora</span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <!-- button end -->
                                            </div>
                                            <!-- swiper slider item txt end -->
                                        </div>
                                    </div>
                                    <!-- swiper slider item end -->
                                    <!-- swiper slider item start -->
                                    <div class="swiper-slide">
                                        <div class="swiper-slide-inner" data-swiper-parallax="50%">
                                            <!-- swiper slider item IMG start -->
                                            <div class="swiper-slide-inner-bg bg-img-3">
                                            </div>
                                            <!-- swiper slider item IMG end -->
                                            <!-- overlay start -->
                                            <div class="overlay overlay-dark-75"></div>
                                            <!-- overlay end -->
                                            <!-- swiper slider item txt start -->
                                            <div class="swiper-slide-inner-txt">
                                                <!-- section subtitle start -->
                                                <a href="#services">
                                                    <h2 class="hero-subheading hero-subheading-home fadeIn-element">
                                                        <span>Debido proceso garantizado</span>
                                                    </h2>
                                                </a>
                                                <!-- section subtitle end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- section title start -->
                                                <h1 class="hero-heading hero-heading-home fadeIn-element">
                                                    Cada sanción, blindada desde el primer borrad<span>o</span>r.
                                                </h1>
                                                <!-- section title end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- button start -->
                                                <div class="more-wraper-center more-wraper-center-home fadeIn-element">
                                                    <a href="/admin/register">
                                                        <div class="more-button-bg-center more-button-circle"></div>
                                                        <div class="more-button-txt-center">
                                                            <span>Empezar ahora</span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <!-- button end -->
                                            </div>
                                            <!-- swiper slider item txt end -->
                                        </div>
                                    </div>
                                    <!-- swiper slider item end -->
                                    <!-- swiper slider item start -->
                                    <div class="swiper-slide">
                                        <div class="swiper-slide-inner" data-swiper-parallax="50%">
                                            <!-- swiper slider item IMG start -->
                                            <div class="swiper-slide-inner-bg bg-img-4">
                                            </div>
                                            <!-- swiper slider item IMG end -->
                                            <!-- overlay start -->
                                            <div class="overlay overlay-dark-75"></div>
                                            <!-- overlay end -->
                                            <!-- swiper slider item txt start -->
                                            <div class="swiper-slide-inner-txt">
                                                <!-- section subtitle start -->
                                                <a href="#services">
                                                    <h2 class="hero-subheading hero-subheading-home fadeIn-element">
                                                        <span>Trazabilidad total</span>
                                                    </h2>
                                                </a>
                                                <!-- section subtitle end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- section title start -->
                                                <h1 class="hero-heading hero-heading-home fadeIn-element">
                                                    Ning<span>ú</span>n plazo legal se le vuelve a pasar.
                                                </h1>
                                                <!-- section title end -->
                                                <!-- divider start -->
                                                <div class="divider-m"></div>
                                                <!-- divider end -->
                                                <!-- button start -->
                                                <div class="more-wraper-center more-wraper-center-home fadeIn-element">
                                                    <a href="/admin/register">
                                                        <div class="more-button-bg-center more-button-circle"></div>
                                                        <div class="more-button-txt-center">
                                                            <span>Empezar ahora</span>
                                                        </div>
                                                    </a>
                                                </div>
                                                <!-- button end -->
                                            </div>
                                            <!-- swiper slider item txt end -->
                                        </div>
                                    </div>
                                    <!-- swiper slider item end -->
                                </div>
                                <!-- swiper wrapper end -->
                            </div>
                            <!-- swiper container end -->
                        </div>
                        <!-- hero slider wrapper end -->
                        <!-- swiper slider controls start -->
                        <div class="hero-slider-bg-controls fadeIn-element">
                            <div class="swiper-slide-controls slide-prev">
                                <div class="ion-ios-arrow-left"></div>
                            </div>
                            <div class="swiper-slide-controls slide-next">
                                <div class="ion-ios-arrow-right"></div>
                            </div>
                        </div>
                        <!-- swiper slider controls end -->
                        <!-- swiper slider pagination start -->
                        <div class="swiper-slide-pagination fadeIn-element"></div>
                        <!-- swiper slider pagination end -->
                        <!-- swiper slider play-pause start -->
                        <div class="swiper-slide-controls-play-pause-wrapper swiper-slide-controls-play-pause slider-on-off fadeIn-element">
                            <div class="slider-on-off-switch">
                                <i class="ion-ios-play"></i>
                            </div>
                            <!-- swiper slider progress start -->
                            <div class="slider-progress-bar">
                                <span>
                                    <svg class="circle-svg" height="50" width="50">
                                        <circle class="circle" cx="25" cy="25" fill="none" r="24" stroke="#e0e0e0" stroke-width="2"></circle>
                                    </svg>
                                </span>
                            </div>
                            <!-- swiper slider progress end -->
                        </div>
                        <!-- swiper slider play-pause end -->
                    </div>
                </div>
            </div>
            <!-- hero bg end -->
            <!-- waves start -->
            <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
                <defs>
                    <path id="gentle-wave-1" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
                </defs>
                <g class="parallax">
                    <use xlink:href="#gentle-wave-1" x="48" y="0" fill="rgba(255, 255, 255, 0.7" />
                    <use xlink:href="#gentle-wave-1" x="48" y="3" fill="rgba(255, 255, 255, 0.5)" />
                    <use xlink:href="#gentle-wave-1" x="48" y="5" fill="rgba(255, 255, 255, 0.3)" />
                    <use xlink:href="#gentle-wave-1" x="48" y="7" fill="#fff" />
                </g>
            </svg>
            <!-- waves end -->
        </div>
        <!-- home end -->
        <!-- vertical lines start -->
        <div class="vertical-lines-wrapper">
            <div class="vertical-lines"></div>
        </div>
        <!-- vertical lines end -->
        <!-- about start -->
        <section id="about" class="section-all bg-light">
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
            <!-- container start -->
            <div class="container">
                <!-- row start -->
                <div class="row">
                    <!-- about wall start -->
                    <div class="wall-images">
                        <img alt="Img" class="wall-photo-1" src="/landing-v2/img/wall/1.jpg">
                        <img alt="Img" class="wall-photo-2" src="/landing-v2/img/wall/2.jpg">
                        <img alt="Img" class="wall-photo-3" src="/landing-v2/img/wall/3.jpg">
                        <img alt="Img" class="wall-photo-4" src="/landing-v2/img/wall/4.jpg">
                        <img alt="Img" class="wall-photo-5" src="/landing-v2/img/wall/5.jpg">
                        <img alt="Img" class="wall-photo-6" src="/landing-v2/img/wall/6.jpg">
                    </div>
                    <!-- about wall end -->
                </div>
                <!-- row end -->
                <!-- row start -->
                <div class="row">
                    <!-- about wall start -->
                    <div class="wall-images-reverse">
                        <img alt="Img" class="wall-photo-1" src="/landing-v2/img/wall/7.jpg">
                        <img alt="Img" class="wall-photo-2" src="/landing-v2/img/wall/8.jpg">
                        <img alt="Img" class="wall-photo-3" src="/landing-v2/img/wall/9.jpg">
                        <img alt="Img" class="wall-photo-4" src="/landing-v2/img/wall/10.jpg">
                        <img alt="Img" class="wall-photo-5" src="/landing-v2/img/wall/11.jpg">
                        <img alt="Img" class="wall-photo-6" src="/landing-v2/img/wall/12.jpg">
                    </div>
                    <!-- about wall end -->
                </div>
                <!-- row end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- section subtitle start -->
                        <h2 class="hero-subheading hero-subheading-dark">
                            <span>01.</span>Quiénes somos
                        </h2>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section title start -->
                        <h2 class="hero-heading hero-heading-dark">
                            Qué es <span>LUPE Legal</span>
                        </h2>
                        <!-- section title end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- quote start -->
                        <div class="testimonial">
                            <div class="inner">
                                <div class="quote">
                                    <blockquote class="quote-inner">
                                        El diferencial no es la IA: es que cada decisión está respaldada por documentos jurídicos reales
                                        - Código Sustantivo del Trabajo, Constitución y jurisprudencia - no por opiniones de un modelo de lenguaje.
                                    </blockquote>
                                </div>
                            </div>
                        </div>
                        <!-- quote end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Nuestra</span> misión
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Que ninguna empresa tenga que elegir entre actuar rápido y actuar bien - que cada sanción, cada citación a descargos y cada reglamento interno quede blindado desde el primer borrador, <a class="link-effect" href="#services">con el debido proceso garantizado</a>.
                            </p>
                        </div>
                        <!-- section txt end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                    </div>
                    <!-- col end -->
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Nuestra</span> visión
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Ser la capa jurídica de confianza detrás de cada decisión disciplinaria y contractual en Latinoamérica - donde la IA acelera el trabajo, pero <a class="link-effect" href="#services">la ley real sigue siendo quien decide qué es correcto</a>.
                            </p>
                        </div>
                        <!-- section txt end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
            <!-- divider start -->
            <!-- divider end -->
        </section>
        <!-- about end -->
        <!-- timeline IMG start -->
        <div class="timeline-image">
            <!-- intro start -->
            <div class="intro-years">
                <!-- section subtitle start -->
                <h2>
                    Artículos del CST y la Constitución
                </h2>
                <!-- section subtitle end -->
                <!-- section title start -->
                <h3 class="facts-counter-number">
                    1472
                </h3>
                <!-- section title end -->
                <!-- section subtitle start -->
                <h4>
                    indexados y citables
                </h4>
                <!-- section subtitle end -->
            </div>
            <!-- intro end -->
        </div>
        <!-- timeline IMG end -->
        <!-- services start -->
        <section id="services" class="section-all bg-dark">
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
            <!-- container start -->
            <div class="container">
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- section subtitle start -->
                        <h2 class="hero-subheading hero-subheading-dark">
                            <span>02.</span>Qué resuelve
                        </h2>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section title start -->
                        <h2 class="hero-heading hero-heading-dark">
                            Los <span>Servicios</span>
                        </h2>
                        <!-- section title end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <div class="services-steps-wrapper">
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Reglamento</span> Interno
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section txt start -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                1
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Construya su RIT completo con IA en minutos, audítelo contra la ley vigente y reciba sugerencias automáticas de actualización.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Procesos</span> disciplinarios
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                2
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Del incidente a la citación de descargos: clasificación de gravedad con IA y detección de casos que requieren manejo especial.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Emitir</span> sanciones
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                3
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                8 validaciones de calidad, verificación de autoridad según su propio RIT y verificación facial de quien autoriza.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                    </div>
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <div class="services-steps-wrapper">
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Contratos</span> y otrosíes
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                4
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Genere contratos a término fijo, indefinido o por obra, y otrosíes de modificación - con alertas automáticas antes de que venza un contrato.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Documentos</span> y notificaciones
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                5
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Citaciones, actas y sanciones en PDF con su propio membrete, notificadas desde su correo corporativo con seguimiento de apertura.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Asistente</span> con IA
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                6
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Sus trabajadores preguntan por chat el estado de su RIT o sus procesos - la IA responde con datos reales de su propia empresa.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                    </div>
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
        </section>
        <!-- services end -->
        <!-- diferenciales start -->
        <section id="diferenciales" class="section-all bg-light">
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
            <!-- container start -->
            <div class="container">
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- section subtitle start -->
                        <h2 class="hero-subheading hero-subheading-dark">
                            <span>03.</span>Diferenciales
                        </h2>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section title start -->
                        <h2 class="hero-heading hero-heading-dark">
                            Por qué <span>elegirnos</span>
                        </h2>
                        <!-- section title end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Fundamento</span> jurídico real
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                No son opiniones de IA: cada salida cita su fuente documental - Constitución, jurisprudencia y Código Sustantivo del Trabajo.
                            </p>
                        </div>
                        <!-- section txt end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                    </div>
                    <!-- col end -->
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Debido</span> proceso garantizado
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Enfoque garantista que protege el proceso y blinda a la empresa frente a demandas y acciones de repetición.
                            </p>
                        </div>
                        <!-- section txt end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Grupos</span> protegidos
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Verifica si el trabajador pertenece a un grupo especialmente protegido por la ley, porque la norma aplica distinto.
                            </p>
                        </div>
                        <!-- section txt end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                    </div>
                    <!-- col end -->
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Opciones</span>, no imposiciones
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                El sistema presenta las medidas posibles con sus riesgos legales; la empresa elige la más adecuada dentro del marco.
                            </p>
                        </div>
                        <!-- section txt end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Auditoría</span> de RIT
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Revisa su reglamento sección por sección y genera una versión mejorada conforme a la ley vigente.
                            </p>
                        </div>
                        <!-- section txt end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                    </div>
                    <!-- col end -->
                    <!-- col start -->
                    <div class="col-md-6 col-all">
                        <!-- section subtitle start -->
                        <h4>
                            <span class="color-switch">Trazabilidad</span> total
                        </h4>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section txt start -->
                        <div class="txt">
                            <p>
                                Cada correo, documento y decisión queda registrado y verificable, listo para soportar cualquier instancia.
                            </p>
                        </div>
                        <!-- section txt end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
        </section>
        <!-- diferenciales end -->
        <!-- como funciona start -->
        <section id="como-funciona" class="section-all bg-dark">
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
            <!-- container start -->
            <div class="container">
                <!-- row start -->
                <div class="row">
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- section subtitle start -->
                        <h2 class="hero-subheading hero-subheading-dark">
                            <span>04.</span>Cómo funciona
                        </h2>
                        <!-- section subtitle end -->
                        <!-- divider start -->
                        <div class="divider-m"></div>
                        <!-- divider end -->
                        <!-- section title start -->
                        <h2 class="hero-heading hero-heading-dark">
                            De la falta a la <span>sanción</span>
                        </h2>
                        <!-- section title end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
                <!-- divider start -->
                <div class="divider-l"></div>
                <!-- divider end -->
                <!-- row start -->
                <div class="row">
                    <div class="services-steps-wrapper">
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Registre</span> el incidente
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                1
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Describa qué pasó - la IA clasifica la gravedad, revisa su RIT y le alerta si el caso requiere manejo especial.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Cite</span> a descargos
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                2
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                El trabajador responde un formulario dinámico; la IA hace seguimiento respetando su derecho de defensa.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                        <!-- divider start -->
                        <div class="divider-l visible-mobile-devices"></div>
                        <!-- divider end -->
                        <!-- col start -->
                        <div class="col-md-4 col-all services-block pull-up">
                            <!-- section subtitle start -->
                            <h4 class="service-heading">
                                <span class="color-switch">Emita</span> y notifique
                            </h4>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section subtitle start -->
                            <div class="service-number">
                                3
                            </div>
                            <!-- section subtitle end -->
                            <!-- section txt start -->
                            <p>
                                Con 8 validaciones de calidad y verificación facial de quien autoriza. El documento sale con su membrete y queda trazado para siempre.
                            </p>
                            <!-- section txt end -->
                        </div>
                        <!-- col end -->
                    </div>
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
        </section>
        <!-- como funciona end -->
        <!-- contact start -->
        <section id="contact" class="section-all">
            <!-- container start -->
            <div class="container-fluid">
                <!-- row start -->
                <div class="row">
                    <!-- parallax wrapper start -->
                    <div class="parallax parallax-all parallax-contact" data-parallax-speed="0.75">
                        <!-- parallax borders start -->
                        <div class="borders"></div>
                        <!-- parallax borders end -->
                        <!-- parallax overlay start -->
                        <div class="parallax-overlay"></div>
                        <!-- parallax overlay end -->
                        <!-- parallax content start -->
                        <div class="parallax-content">
                            <!-- section subtitle start -->
                            <h2 class="hero-subheading">
                                <span>05.</span>Hablemos
                            </h2>
                            <!-- section subtitle end -->
                            <!-- divider start -->
                            <div class="divider-m"></div>
                            <!-- divider end -->
                            <!-- section title start -->
                            <h2 class="hero-heading">
                                El <span>Contacto</span>
                            </h2>
                            <!-- section title end -->
                            <!-- divider start -->
                            <div class="divider-l"></div>
                            <!-- divider end -->
                            <!-- contact info start -->
                            <div class="contact-info-wrapper">
                                <!-- col start -->
                                <div class="col-md-12 col-sm-12">
                                    <div class="contact-info-description">
                                        <i class="ion-ios-email-outline contact-info-description-img large"></i>
                                        <!-- divider start -->
                                        <div class="divider-m"></div>
                                        <!-- divider end -->
                                        <span class="contact-info-text large"><a class="link-effect link-effect-light" href="mailto:admin@ceslegal.co">admin@ceslegal.co</a></span>
                                    </div>
                                    <!-- divider start -->
                                    <div class="divider-m visible-mobile-devices"></div>
                                    <!-- divider end -->
                                </div>
                                <!-- col end -->
                                <!-- col start -->
                                <div class="col-md-12 col-sm-12">
                                    <!-- divider start -->
                                    <div class="divider-l"></div>
                                    <!-- divider end -->
                                    <div class="contact-info-description">
                                        <!-- button start -->
                                        <div class="more-wraper-center">
                                            <a href="/admin/register">
                                                <div class="more-button-bg-center more-button-circle"></div>
                                                <div class="more-button-txt-center">
                                                    <span>Registrarse</span>
                                                </div>
                                            </a>
                                        </div>
                                        <!-- button end -->
                                    </div>
                                </div>
                                <!-- col end -->
                            </div>
                            <!-- contact info end -->
                        </div>
                        <!-- parallax content end -->
                        <!-- waves start -->
                        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                            viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
                            <defs>
                                <path id="gentle-wave-5" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
                            </defs>
                            <g class="parallax">
                                <use xlink:href="#gentle-wave-5" x="48" y="0" fill="rgba(255, 255, 255, 0.7" />
                                <use xlink:href="#gentle-wave-5" x="48" y="3" fill="rgba(255, 255, 255, 0.5)" />
                                <use xlink:href="#gentle-wave-5" x="48" y="5" fill="rgba(255, 255, 255, 0.3)" />
                                <use xlink:href="#gentle-wave-5" x="48" y="7" fill="#fff" />
                            </g>
                        </svg>
                        <!-- waves end -->
                    </div>
                    <!-- parallax wrapper end -->
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
        </section>
        <!-- contact end -->
        <!-- footer start -->
        <section id="footer" class="section-all bg-light">
            <!-- divider start -->
            <div class="divider-xl"></div>
            <!-- divider end -->
            <!-- container start -->
            <div class="container-fluid">
                <!-- row start -->
                <div class="row footer-credits">
                    <!-- footer logo start -->
                    <div class="footer-credits-logo">
                        <a href="/"><img alt="LUPE Legal" src="/landing-v2/img/logo-footer.png?v=3"></a>
                    </div>
                    <!-- footer logo end -->
                    <!-- divider start -->
                    <div class="divider-l"></div>
                    <!-- divider end -->
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- social icons start -->
                        <div class="social-icons">
                            <ul>
                                <li>
                                    <a class="ion-earth" href="https://ceslegal.co" rel="noopener"><span>Sitio web</span></a>
                                </li>
                                <li>
                                    <a class="ion-email" href="mailto:admin@ceslegal.co"><span>Correo</span></a>
                                </li>
                            </ul>
                        </div>
                        <!-- social icons end -->
                        <!-- divider start -->
                        <div class="divider-l"></div>
                        <!-- divider end -->
                    </div>
                    <!-- col end -->
                    <!-- col start -->
                    <div class="col-lg-12">
                        <!-- copyright start -->
                        <div class="copyright">
&copy; {{ date('Y') }} LUPE Legal. Todos los derechos reservados.
                        </div>
                        <!-- copyright end -->
                    </div>
                    <!-- col end -->
                </div>
                <!-- row end -->
            </div>
            <!-- container end -->
            <!-- divider start -->
            <div class="divider-l"></div>
            <!-- divider end -->
        </section>
        <!-- footer end -->
        <!-- to top arrow start -->
        <a href="#home">
            <div class="to-top-arrow">
                <span class="ion-ios-arrow-up"></span>
            </div>
        </a>
        <!-- to top arrow end -->
        <!-- scripts start -->
        <script src="/landing-v2/js/plugins.js"></script>
        <script src="/landing-v2/js/brandex-01.js"></script>
        <!-- scripts end -->
    </body>
</html>
