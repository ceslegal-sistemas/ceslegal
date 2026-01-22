/**
 * Tour de onboarding para el módulo de Descargos
 * Usa Driver.js - https://driverjs.com
 */

document.addEventListener("DOMContentLoaded", function () {
    const pathname = window.location.pathname;

    // Determinar en qué página estamos
    const isAdminDashboard = pathname === "/admin" || pathname === "/admin/";
    const isProcesoDisciplinarios = pathname.includes("proceso-disciplinarios");
    const isTrabajadores = pathname.includes("trabajadors");

    // Solo ejecutar en las páginas del dashboard o proceso-disciplinarios
    if (!isAdminDashboard && !isProcesoDisciplinarios && !isTrabajadores) {
        return;
    }

    // Driver.js se carga via CDN, acceder desde window
    const driverFn = window.driver.js.driver;

    // Marcar dinámicamente el elemento del menú para el tour del dashboard
    if (isAdminDashboard) {
        // Buscar el enlace del menú "Historial de Descargos" y agregar el atributo data-tour
        const menuLinks = document.querySelectorAll(".fi-sidebar-nav a");
        menuLinks.forEach(function (link) {
            if (
                link.textContent.includes("Historial de Descargos") ||
                link.href.includes("proceso-disciplinarios")
            ) {
                link.setAttribute(
                    "data-tour",
                    "menu-historial-proceso-disciplinario",
                );
            }

            if (
                link.textContent.includes("Crear Descargos") ||
                link.href.includes("proceso-disciplinarios/create")
            ) {
                link.setAttribute(
                    "data-tour",
                    "menu-crear-proceso-disciplinario",
                );
            }
        });

        // Buscar el enlace del menú "Trabajadores" y agregar el atributo data-tour
        const menuLinksTrabajadores =
            document.querySelectorAll(".fi-sidebar-nav a");
        menuLinksTrabajadores.forEach(function (link) {
            if (
                link.textContent.includes("Trabajadores") ||
                link.href.includes("trabajadors")
            ) {
                link.setAttribute("data-tour", "menu-trabajadores");
            }
        });
    }

    // ========== TOUR PARA INICIO (DASHBOARD) ==========
    if (isAdminDashboard) {
        const tourInicio = driverFn({
            showProgress: true,
            nextBtnText: "Siguiente",
            prevBtnText: "Anterior",
            doneBtnText: "Entendido",
            progressText: "Paso {{current}} de {{total}}",
            steps: [
                {
                    popover: {
                        title: "Bienvenido al Sistema de Descargos",
                        description:
                            "Aquí gestionas todo el proceso de descargos: desde la citación hasta la sanción.",
                    },
                },
                {
                    element:
                        "[data-tour='menu-crear-proceso-disciplinario']",
                    popover: {
                        title: "Accede Crear de Descargos",
                        description: "Aquí puedes crear los descargos.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='menu-historial-proceso-disciplinario']",
                    popover: {
                        title: "Accede al Historial de Descargos",
                        description:
                            "Aquí puedes ver y gestionar los procesos disciplinarios.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='menu-trabajadores']",
                    popover: {
                        title: "Gestiona los Trabajadores",
                        description:
                            "Aquí puedes ver y administrar tus trabajadores.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='help-button-dashboard']",
                    popover: {
                        title: "¿Necesitas ayuda?",
                        description:
                            "Puedes ver este tutorial de nuevo en cualquier momento.",
                        side: "bottom",
                    },
                },
                {
                    popover: {
                        title: "¡Listo para comenzar!",
                        description:
                            "Ya conoces lo básico. Dirígete a los Descargos para gestionar el proceso.",
                    },
                },
            ],
        });

        // Guardar referencia global
        window.tourDescargosInicio = tourInicio;

        // Iniciar tour automáticamente si es primera vez
        const tourInicioShown = localStorage.getItem(
            "tourDescargosInicioShown",
        );

        if (!tourInicioShown) {
            setTimeout(function () {
                tourInicio.drive();
                localStorage.setItem("tourDescargosInicioShown", "true");
            }, 1000);
        }
    }

    // ========== TOUR PARA LISTA DE TRABAJADORES ==========
    if (pathname.endsWith("trabajadors") || pathname.endsWith("trabajadors/")) {
        const tourTrabajadores = driverFn({
            showProgress: true,
            nextBtnText: "Siguiente",
            prevBtnText: "Anterior",
            doneBtnText: "Entendido",
            progressText: "Paso {{current}} de {{total}}",
            steps: [
                {
                    popover: {
                        title: "Bienvenido a la Gestión de Trabajadores",
                        description:
                            "Aquí puedes ver y administrar todos los trabajadores registrados en el sistema.",
                    },
                },
                {
                    element: ".fi-ta-table",
                    popover: {
                        title: "Tu lista de trabajadores",
                        description:
                            "Cada fila muestra información clave del trabajador y acciones disponibles.",
                        side: "top",
                    },
                },
                {
                    element: ".fi-ta-header-ctn",
                    popover: {
                        title: "Busca y filtra",
                        description:
                            "Usa los filtros para encontrar trabajadores por nombre, empresa o área.",
                        side: "bottom",
                    },
                },
                {
                    element: "[data-tour='create-button-trabajadores']",
                    popover: {
                        title: "Añadir nuevo trabajador",
                        description:
                            "Aquí puedes registrar un nuevo trabajador en el sistema.",
                        side: "bottom",
                    },
                },
                {
                    element: "[data-tour='help-button-trabajadores']",
                    popover: {
                        title: "¿Necesitas ayuda?",
                        description:
                            "Puedes ver este tutorial de nuevo en cualquier momento.",
                        side: "bottom",
                    },
                },
                {
                    popover: {
                        title: "¡Listo para comenzar!",
                        description:
                            "Ya conoces lo básico. Explora los trabajadores registrados o añade nuevos.",
                    },
                },
            ],
        });

        // Guardar referencia global
        window.tourTrabajadores = tourTrabajadores;

        // Iniciar tour automáticamente si es primera vez
        const tourTrabajadoresShown = localStorage.getItem(
            "tourTrabajadoresShown",
        );
        if (!tourTrabajadoresShown) {
            setTimeout(function () {
                tourTrabajadores.drive();
                localStorage.setItem("tourTrabajadoresShown", "true");
            }, 1000);
        }
    }

    // ========== TOUR PARA CREAR TRABAJADOR ==========
    if (pathname.includes("trabajadors/create")) {
        const tourCreateTrabajador = driverFn({
            showProgress: true,
            nextBtnText: "Siguiente",
            prevBtnText: "Anterior",
            doneBtnText: "Entendido",
            progressText: "Paso {{current}} de {{total}}",
            steps: [
                {
                    popover: {
                        title: "Registrar nuevo trabajador",
                        description:
                            "Completa este formulario para añadir un trabajador al sistema. Sus datos serán usados en los procesos de descargos.",
                    },
                },
                {
                    element: '[data-tour="trabajador-empresa"]',
                    popover: {
                        title: "Paso 1: Empresa",
                        description:
                            "Selecciona la empresa a la que pertenece el trabajador. Si eres cliente, ya está seleccionada.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-tipo-doc"]',
                    popover: {
                        title: "Paso 2: Tipo de documento",
                        description:
                            "Selecciona el tipo de documento de identidad: Cédula, Cédula de Extranjería, etc.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-numero-doc"]',
                    popover: {
                        title: "Paso 3: Número de documento",
                        description:
                            "Ingresa el número de documento. Este debe ser único en el sistema.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-genero"]',
                    popover: {
                        title: "Paso 4: Género",
                        description: "Selecciona el género del trabajador.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-nombres"]',
                    popover: {
                        title: "Paso 5: Nombres",
                        description:
                            "Escribe los nombres completos del trabajador.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-apellidos"]',
                    popover: {
                        title: "Paso 6: Apellidos",
                        description:
                            "Escribe los apellidos completos del trabajador.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-departamento-nacimiento"]',
                    popover: {
                        title: "Departamento de Nacimiento (Opcional)",
                        description:
                            "Selecciona el departamento donde nació el trabajador.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-ciudad-nacimiento"]',
                    popover: {
                        title: "Ciudad / Municipio de Nacimiento (Opcional)",
                        description:
                            "Selecciona la ciudad o municipio donde nació el trabajador.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-email"]',
                    popover: {
                        title: "Paso 7: Correo electrónico",
                        description:
                            "El correo es importante porque aquí se enviarán las citaciones a descargos.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-cargo"]',
                    popover: {
                        title: "Paso 8: Cargo",
                        description:
                            "Selecciona el cargo del trabajador o elige 'Otro' para personalizarlo.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-cargo-otro"]',
                    popover: {
                        title: "Otro Cargo",
                        description:
                            "Si seleccionaste 'Otro' en el paso anterior, escribe el cargo específico.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='trabajador-area']",
                    popover: {
                        title: "Área (Opcional)",
                        description:
                            "Puedes especificar el área o departamento donde trabaja el empleado.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='trabajador-area-otro']",
                    popover: {
                        title: "Otra Área (Opcional)",
                        description:
                            "Si seleccionaste 'Otro' en el paso anterior, escribe el área específica.",
                        side: "right",
                    },
                },
                {
                    element: "[data-tour='trabajador-activo']",
                    popover: {
                        title: "Estado del trabajador",
                        description:
                            "Asegúrate de que 'Activo' esté seleccionado si el trabajador aún labora en la empresa.",
                        side: "right",
                    },
                },
                {
                    element: ".fi-form-actions",
                    popover: {
                        title: "Paso 9: Crear trabajador",
                        description:
                            "Al hacer clic en 'Crear', el trabajador quedará registrado y podrás citarlo a descargos.",
                        side: "top",
                    },
                },
                {
                    popover: {
                        title: "¡Listo!",
                        description:
                            "Completa los datos y el trabajador será añadido al sistema.",
                    },
                },
            ],
        });

        window.tourCreateTrabajador = tourCreateTrabajador;

        // Iniciar tour automáticamente si es primera vez
        const tourCreateTrabajadorShown = localStorage.getItem(
            "tourCreateTrabajadorShown",
        );
        if (!tourCreateTrabajadorShown) {
            setTimeout(function () {
                tourCreateTrabajador.drive();
                localStorage.setItem("tourCreateTrabajadorShown", "true");
            }, 1000);
        }
    }

    // ========== TOUR PARA LISTA DE PROCESOS DISCIPLINARIOS ==========
    if (
        pathname.endsWith("proceso-disciplinarios") ||
        pathname.endsWith("proceso-disciplinarios/")
    ) {
        const tourList = driverFn({
            showProgress: true,
            nextBtnText: "Siguiente",
            prevBtnText: "Anterior",
            doneBtnText: "Entendido",
            progressText: "Paso {{current}} de {{total}}",
            steps: [
                {
                    popover: {
                        title: "Bienvenido al Sistema de Descargos",
                        description:
                            "Aquí gestionas todo el proceso de descargos: desde la citación hasta la sanción.",
                    },
                },
                {
                    element: ".fi-ta-table",
                    popover: {
                        title: "Tu lista de procesos",
                        description:
                            "Cada fila muestra el trabajador, estado del proceso y acciones disponibles.",
                        side: "top",
                    },
                },
                {
                    element: ".fi-ta-header-ctn",
                    popover: {
                        title: "Busca y filtra",
                        description:
                            "Usa los filtros para encontrar procesos por estado, modalidad o empresa.",
                        side: "bottom",
                    },
                },
                {
                    element: "[data-tour='create-button']",
                    popover: {
                        title: "Crear nuevo descargo",
                        description:
                            "Aquí puedes citar a un trabajador a descargos.",
                        side: "bottom",
                    },
                },
                {
                    element: "[data-tour='help-button']",
                    popover: {
                        title: "¿Necesitas ayuda?",
                        description:
                            "Puedes ver este tutorial de nuevo en cualquier momento.",
                        side: "bottom",
                    },
                },
                {
                    popover: {
                        title: "¡Listo para comenzar!",
                        description:
                            "Ya conoces lo básico. Crea tu primer proceso o explora los existentes.",
                    },
                },
            ],
        });

        // Guardar referencia global
        window.tourDescargos = tourList;

        // Iniciar tour automáticamente si es primera vez
        const tourShown = localStorage.getItem("tourDescargosListShown");
        if (!tourShown) {
            setTimeout(function () {
                tourList.drive();
                localStorage.setItem("tourDescargosListShown", "true");
            }, 1000);
        }
    }

    // ========== TOUR PARA CREAR ==========
    if (pathname.includes("proceso-disciplinarios/create")) {
        const tourCreate = driverFn({
            showProgress: true,
            nextBtnText: "Siguiente",
            prevBtnText: "Anterior",
            doneBtnText: "Entendido",
            progressText: "Paso {{current}} de {{total}}",
            steps: [
                {
                    popover: {
                        title: "Crear un proceso de descargos",
                        description:
                            "Completa este formulario para citar a un trabajador. Al guardar, el sistema enviará la citación por correo.",
                    },
                },
                {
                    element: '[data-tour="empresa-select"]',
                    popover: {
                        title: "Paso 1: Empresa",
                        description:
                            "Tu empresa ya está seleccionada automáticamente. Continúa al siguiente paso.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-select"]',
                    popover: {
                        title: "Paso 2: Trabajador",
                        description:
                            "Busca y selecciona al trabajador. Escribe el nombre para encontrarlo más rápido.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="trabajador-create"]',
                    popover: {
                        title: "¿No lo encuentras?",
                        description:
                            "Haz clic en '+' para registrar un trabajador nuevo.",
                        side: "left",
                    },
                },
                {
                    element: '[data-tour="modalidad-select"]',
                    popover: {
                        title: "Paso 3: Modalidad",
                        description:
                            "Elige cómo será la audiencia: presencial, virtual o telefónica.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="abogado-select"]',
                    popover: {
                        title: "Asignación de Abogado",
                        description:
                            "Si seleccionas Presencial o Telefónico, podrás seleccionar el abogado y su disponibilidad que atenderá la diligencia.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="motivos-select"]',
                    popover: {
                        title: "Paso 4: Motivos",
                        description:
                            "Elige uno o más motivos que justifican la citación.",
                        side: "top",
                    },
                },
                {
                    element: '[data-tour="fecha-ocurrencia"]',
                    popover: {
                        title: "Paso 5: Fecha de los hechos",
                        description:
                            "Indica cuándo ocurrieron los hechos que motivan el proceso.",
                        side: "right",
                    },
                },
                {
                    element: '[data-tour="hechos-editor"]',
                    popover: {
                        title: "Paso 6: Descripción de los hechos",
                        description:
                            "Explica qué pasó, dónde y quiénes estuvieron involucrados.",
                        side: "top",
                    },
                },
                {
                    element: '[data-tour="ia-button"]',
                    popover: {
                        title: "Asistente con IA",
                        description:
                            "¿No sabes cómo redactarlo? La IA mejora tu texto automáticamente.",
                        side: "left",
                    },
                },
                {
                    element: ".fi-form-actions",
                    popover: {
                        title: "Paso 7: Guardar",
                        description:
                            "Al hacer clic en 'Crear', se generará la citación y se enviará al trabajador.",
                        side: "top",
                    },
                },
                {
                    popover: {
                        title: "¡Ya estás listo!",
                        description:
                            "Completa el formulario y el sistema se encargará del resto.",
                    },
                },
            ],
        });

        window.tourDescargosCreate = tourCreate;

        // Iniciar tour automáticamente si es primera vez
        const tourCreateShown = localStorage.getItem(
            "tourDescargosCreateShown",
        );
        if (!tourCreateShown) {
            setTimeout(function () {
                tourCreate.drive();
                localStorage.setItem("tourDescargosCreateShown", "true");
            }, 1000);
        }
    }

    // ========== FUNCIONES GLOBALES ==========
    // Permitir reiniciar el tour manualmente
    window.reiniciarTourDescargos = function () {
        localStorage.removeItem("tourDescargosListShown");
        localStorage.removeItem("tourDescargosCreateShown");
        localStorage.removeItem("tourDescargosInicioShown");
        localStorage.removeItem("tourTrabajadoresShown");
        localStorage.removeItem("tourCreateTrabajadorShown");
        location.reload();
    };

    // Iniciar tour manualmente
    window.iniciarTour = function () {
        if (window.tourDescargos) {
            window.tourDescargos.drive();
        } else if (window.tourDescargosCreate) {
            window.tourDescargosCreate.drive();
        } else if (window.tourDescargosInicio) {
            window.tourDescargosInicio.drive();
        } else if (window.tourTrabajadores) {
            window.tourTrabajadores.drive();
        } else if (window.tourCreateTrabajador) {
            window.tourCreateTrabajador.drive();
        }
    };
});
