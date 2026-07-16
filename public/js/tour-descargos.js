/**
 * Guías interactivas de CES Legal (Driver.js - https://driverjs.com)
 *
 * Las guías se adaptan al ROL del usuario autenticado, que llega en
 * window.CES_USER = { role, esBufete, empresaActiva, nombre } (inyectado por
 * AdminPanelProvider). Roles con guía: 'cliente', 'bufete', 'super_admin'.
 * El texto va en español colombiano (usted).
 */
document.addEventListener("DOMContentLoaded", function () {
    const pathname = window.location.pathname;

    const CES = window.CES_USER || {
        role: "cliente",
        esBufete: false,
        empresaActiva: false,
        nombre: "",
    };
    const role = CES.role || "cliente";

    // ── Página actual ────────────────────────────────────────────────────────
    const isAdminDashboard = pathname === "/admin" || pathname === "/admin/";
    const isProceso = pathname.includes("proceso-disciplinarios");
    const isProcesoList =
        pathname.endsWith("proceso-disciplinarios") ||
        pathname.endsWith("proceso-disciplinarios/");
    const isTrabajadores = pathname.includes("trabajadors");
    const isTrabajadoresList =
        pathname.endsWith("trabajadors") || pathname.endsWith("trabajadors/");
    const isTrabajadorCreate = pathname.includes("trabajadors/create");

    if (!isAdminDashboard && !isProceso && !isTrabajadores) return;
    if (!window.driver || !window.driver.js) return;
    const driverFn = window.driver.js.driver;

    // ── Utilidades ───────────────────────────────────────────────────────────
    const base = {
        showProgress: true,
        nextBtnText: "Siguiente",
        prevBtnText: "Anterior",
        doneBtnText: "Entendido",
        progressText: "Paso {{current}} de {{total}}",
    };

    // Etiqueta cada enlace del menú lateral con un data-tour estable, según su
    // texto/href, para poder apuntarle desde los pasos sin acoplarse al orden.
    function tagSidebar() {
        function tag(el, name) {
            if (el && !el.getAttribute("data-tour")) {
                el.setAttribute("data-tour", name);
            }
        }
        document.querySelectorAll(".fi-sidebar-nav a").forEach(function (link) {
            const t = link.textContent || "";
            const h = link.getAttribute("href") || "";
            if (h.includes("proceso-disciplinarios/create") || t.includes("Crear Descargos"))
                tag(link, "menu-crear");
            else if (h.includes("proceso-disciplinarios") || t.includes("Historial de Descargos"))
                tag(link, "menu-historial");
            if (h.includes("trabajadors") || t.includes("Trabajadores"))
                tag(link, "menu-trabajadores");
            if (h.includes("reglamento") || t.includes("Reglamento"))
                tag(link, "menu-reglamento");
            if (/\/empresas(\b|\/|\?)/.test(h) || t.trim() === "Empresas")
                tag(link, "menu-empresas");
            if (h.includes("users") || t.includes("Usuarios"))
                tag(link, "menu-usuarios");
            if (h.includes("roles") || h.includes("shield") || t.trim() === "Roles")
                tag(link, "menu-roles");
        });
    }

    // Conserva solo los pasos cuyo elemento existe en la página (o sin elemento),
    // para que la guía no falle si un ítem no aplica a este rol.
    function present(steps) {
        return steps.filter(function (s) {
            return !s.element || !!document.querySelector(s.element);
        });
    }

    function build(steps) {
        const clean = present(steps);
        if (!clean.length) return null;
        return driverFn(Object.assign({}, base, { steps: clean }));
    }

    // Guarda la instancia activa para el botón "¿Necesita ayuda?" / "¿Cómo funciona?".
    let activeTour = null;
    function autoStart(tour, storageKey) {
        if (!tour) return;
        activeTour = tour;
        if (!localStorage.getItem(storageKey)) {
            setTimeout(function () {
                tour.drive();
                localStorage.setItem(storageKey, "true");
            }, 1200);
        }
    }

    const hola = CES.nombre ? "Hola, " + CES.nombre + ". " : "";

    // ══════════════════════════════════════════════════════════════════════════
    // INICIO (Dashboard) - guía por rol
    // ══════════════════════════════════════════════════════════════════════════
    if (isAdminDashboard) {
        tagSidebar();

        let steps;
        if (role === "super_admin") {
            steps = [
                { popover: { title: "Panel de administración", description: hola + "Desde aquí supervisa las empresas, los usuarios y todos los procesos del sistema." } },
                { element: "[data-tour='menu-empresas']", popover: { title: "Empresas", description: "Cree y administre las empresas clientes.", side: "right" } },
                { element: "[data-tour='menu-usuarios']", popover: { title: "Usuarios", description: "Gestione las cuentas y sus accesos al sistema.", side: "right" } },
                { element: "[data-tour='menu-roles']", popover: { title: "Roles y permisos", description: "Defina qué puede hacer cada rol.", side: "right" } },
                { element: "[data-tour='menu-historial']", popover: { title: "Historial de Descargos", description: "Supervise todos los procesos disciplinarios del sistema.", side: "right" } },
                { element: "[data-tour='help-button-dashboard']", popover: { title: "¿Necesita ayuda?", description: "Puede reabrir esta guía cuando quiera.", side: "bottom" } },
                { popover: { title: "Listo", description: "Ya conoce el panel de administración." } },
            ];
        } else if (role === "bufete") {
            steps = [
                { popover: { title: "Bienvenido a CES Legal", description: hola + "Como bufete, gestiona los procesos disciplinarios de varias empresas desde un mismo lugar." } },
                { element: ".se-wrap", popover: { title: "Primero elija la empresa", description: "En la barra superior seleccione la empresa sobre la que va a trabajar. Todo lo que haga aplicará a esa empresa.", side: "bottom" } },
                { element: "[data-tour='menu-empresas']", popover: { title: "Sus empresas", description: "Cree y administre las empresas que representa.", side: "right" } },
                { element: ".pg-wrap", popover: { title: "El proceso paso a paso", description: "Esta guía le muestra en qué punto va la empresa seleccionada y cuál es el siguiente paso.", side: "top" } },
                { element: "[data-tour='menu-reglamento']", popover: { title: "Reglamento Interno", description: "Suba o construya el RIT de la empresa seleccionada. Es la base para poder sancionar.", side: "right" } },
                { element: "[data-tour='menu-crear']", popover: { title: "Crear Descargos", description: "Inicie un proceso disciplinario citando a un trabajador.", side: "right" } },
                { element: "[data-tour='menu-historial']", popover: { title: "Historial de Descargos", description: "Revise y continúe los procesos en curso, y emita la sanción cuando corresponda.", side: "right" } },
                { element: "[data-tour='menu-trabajadores']", popover: { title: "Trabajadores", description: "Administre los trabajadores de la empresa seleccionada.", side: "right" } },
                { element: "[data-tour='help-button-dashboard']", popover: { title: "¿Necesita ayuda?", description: "Puede reabrir esta guía cuando quiera.", side: "bottom" } },
                { popover: { title: "Listo para comenzar", description: "Seleccione una empresa y siga la guía de su proceso." } },
            ];
        } else {
            // cliente
            steps = [
                { popover: { title: "Bienvenido a CES Legal", description: hola + "Aquí gestiona todo el proceso disciplinario de su empresa: desde el reglamento hasta la sanción." } },
                { element: ".pg-wrap", popover: { title: "Su proceso paso a paso", description: "Esta guía le indica en qué punto va y cuál es el siguiente paso a seguir.", side: "top" } },
                { element: "[data-tour='menu-reglamento']", popover: { title: "Reglamento Interno", description: "Suba o construya el RIT de su empresa. Es la base para poder aplicar sanciones.", side: "right" } },
                { element: "[data-tour='menu-crear']", popover: { title: "Crear Descargos", description: "Cite a un trabajador a descargos por un hecho ocurrido.", side: "right" } },
                { element: "[data-tour='menu-historial']", popover: { title: "Historial de Descargos", description: "Revise el estado de cada proceso y emita la sanción cuando corresponda.", side: "right" } },
                { element: "[data-tour='menu-trabajadores']", popover: { title: "Trabajadores", description: "Administre los trabajadores de su empresa.", side: "right" } },
                { element: "[data-tour='help-button-dashboard']", popover: { title: "¿Necesita ayuda?", description: "Puede reabrir esta guía cuando quiera.", side: "bottom" } },
                { popover: { title: "Listo para comenzar", description: "Siga la guía de su proceso para avanzar paso a paso." } },
            ];
        }

        autoStart(build(steps), "cesTourInicio_" + role);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LISTA DE PROCESOS (Historial de Descargos)
    // ══════════════════════════════════════════════════════════════════════════
    if (isProcesoList) {
        const esBufeteSinEmpresa = role === "bufete" && !CES.empresaActiva;
        const introDesc = esBufeteSinEmpresa
            ? "Aquí verá los procesos de la empresa que seleccione en la barra superior."
            : "Aquí gestiona todo el proceso: desde la citación hasta la sanción.";

        const steps = [
            { popover: { title: "Historial de Descargos", description: introDesc } },
            { element: ".fi-ta-table", popover: { title: "Su lista de procesos", description: "Cada fila muestra el trabajador, el estado del proceso y las acciones disponibles.", side: "top" } },
            { element: ".fi-ta-header-ctn", popover: { title: "Busque y filtre", description: "Use los filtros para encontrar procesos por estado, modalidad o empresa.", side: "bottom" } },
            { element: "[data-tour='create-button']", popover: { title: "Crear un descargo", description: "Cite a un trabajador a descargos para iniciar un proceso.", side: "bottom" } },
            { element: "[data-tour='help-button']", popover: { title: "¿Necesita ayuda?", description: "Puede reabrir esta guía cuando quiera.", side: "bottom" } },
            { popover: { title: "Listo", description: "Cree un proceso nuevo o continúe con los existentes." } },
        ];

        autoStart(build(steps), "cesTourProcesoList_" + role);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LISTA DE TRABAJADORES
    // ══════════════════════════════════════════════════════════════════════════
    if (isTrabajadoresList) {
        const steps = [
            { popover: { title: "Trabajadores", description: "Aquí ve y administra los trabajadores registrados." } },
            { element: ".fi-ta-table", popover: { title: "Su lista de trabajadores", description: "Cada fila muestra la información clave del trabajador y sus acciones.", side: "top" } },
            { element: ".fi-ta-header-ctn", popover: { title: "Busque y filtre", description: "Use los filtros para encontrar trabajadores por nombre o área.", side: "bottom" } },
            { element: "[data-tour='help-button-trabajadores']", popover: { title: "¿Necesita ayuda?", description: "Puede reabrir esta guía cuando quiera.", side: "bottom" } },
            { popover: { title: "Listo", description: "Los trabajadores también se pueden crear al momento de citar a descargos." } },
        ];

        autoStart(build(steps), "cesTourTrabajadoresList_" + role);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREAR TRABAJADOR
    // ══════════════════════════════════════════════════════════════════════════
    if (isTrabajadorCreate) {
        const empresaDesc =
            role === "bufete"
                ? "Seleccione la empresa a la que pertenece el trabajador."
                : "La empresa ya está seleccionada automáticamente.";

        const steps = [
            { popover: { title: "Registrar un trabajador", description: "Complete este formulario para añadir un trabajador. Sus datos se usan en los procesos de descargos." } },
            { element: '[data-tour="trabajador-empresa"]', popover: { title: "Empresa", description: empresaDesc, side: "right" } },
            { element: '[data-tour="trabajador-tipo-doc"]', popover: { title: "Tipo de documento", description: "Seleccione el tipo de documento de identidad.", side: "right" } },
            { element: '[data-tour="trabajador-numero-doc"]', popover: { title: "Número de documento", description: "Ingrese el número de documento. Debe ser único en el sistema.", side: "right" } },
            { element: '[data-tour="trabajador-genero"]', popover: { title: "Género", description: "Seleccione el género del trabajador.", side: "right" } },
            { element: '[data-tour="trabajador-nombres"]', popover: { title: "Nombres", description: "Escriba los nombres completos del trabajador.", side: "right" } },
            { element: '[data-tour="trabajador-apellidos"]', popover: { title: "Apellidos", description: "Escriba los apellidos completos del trabajador.", side: "right" } },
            { element: '[data-tour="trabajador-email"]', popover: { title: "Correo electrónico", description: "Aquí se enviarán las citaciones a descargos, así que es importante.", side: "right" } },
            { element: '[data-tour="trabajador-cargo"]', popover: { title: "Cargo", description: "Seleccione el cargo del trabajador o elija 'Otro' para escribirlo.", side: "right" } },
            { element: "[data-tour='trabajador-area']", popover: { title: "Área (opcional)", description: "Puede indicar el área o departamento del trabajador.", side: "right" } },
            { element: "[data-tour='trabajador-activo']", popover: { title: "Estado", description: "Deje 'Activo' si el trabajador aún labora en la empresa.", side: "right" } },
            { element: ".fi-form-actions", popover: { title: "Guardar", description: "Al hacer clic en 'Crear', el trabajador queda registrado y podrá citarlo a descargos.", side: "top" } },
            { popover: { title: "Listo", description: "Complete los datos y el trabajador quedará añadido." } },
        ];

        autoStart(build(steps), "cesTourTrabajadorCreate_" + role);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Funciones globales
    // ══════════════════════════════════════════════════════════════════════════
    window.iniciarTour = function () {
        if (activeTour) activeTour.drive();
    };

    window.reiniciarTourDescargos = function () {
        Object.keys(localStorage)
            .filter(function (k) {
                return k.indexOf("cesTour") === 0;
            })
            .forEach(function (k) {
                localStorage.removeItem(k);
            });
        location.reload();
    };
});
