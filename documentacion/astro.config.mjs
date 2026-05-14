// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	site: 'https://ceslegal.renbel.com.co',
	base: '/docs',
	outDir: '../public/docs',
	build: {
		format: 'directory',
	},
	integrations: [
		starlight({
			title: '',
			description: 'Sistema de Gestión de Procesos Disciplinarios Laborales',
			defaultLocale: 'root',
			locales: {
				root: {
					label: 'Español',
					lang: 'es',
				},
			},
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/juanparen15/ces-legal' },
			],
			logo: {
				src: './src/assets/logo.png',
				replacesTitle: false,
			},
			customCss: [
				'./src/styles/custom.css',
			],
			sidebar: [
				{
					label: 'Inicio',
					items: [
						{ label: 'Introducción', slug: 'inicio/introduccion' },
						{ label: 'Instalación', slug: 'inicio/instalacion' },
						{ label: 'Configuración', slug: 'inicio/configuracion' },
						{ label: 'Despliegue', slug: 'inicio/despliegue' },
					],
				},
				{
					label: 'Arquitectura',
					items: [
						{ label: 'Visión General', slug: 'arquitectura/vision-general' },
						{ label: 'Stack Tecnológico', slug: 'arquitectura/stack-tecnologico' },
						{ label: 'Estructura del Proyecto', slug: 'arquitectura/estructura-proyecto' },
						{ label: 'Base de Datos', slug: 'arquitectura/base-datos' },
						{ label: 'Servicios', slug: 'arquitectura/servicios' },
					],
				},
				{
					label: 'Flujo del Proceso',
					items: [
						{ label: 'Estados del Proceso', slug: 'flujo/estados-proceso' },
						{ label: 'Diagrama de Flujo', slug: 'flujo/diagrama-flujo' },
						{ label: 'Reglas de Negocio', slug: 'flujo/reglas-negocio' },
					],
				},
				{
					label: 'Módulos',
					items: [
						{ label: 'Procesos Disciplinarios', slug: 'modulos/procesos-disciplinarios' },
						{ label: 'Trabajadores', slug: 'modulos/trabajadores' },
						{ label: 'Empresas', slug: 'modulos/empresas' },
						{ label: 'Diligencias de Descargos', slug: 'modulos/diligencias-descargos' },
						{ label: 'Sanciones', slug: 'modulos/sanciones' },
						{ label: 'Documentos', slug: 'modulos/documentos' },
						{ label: 'Notificaciones', slug: 'modulos/notificaciones' },
						{ label: 'Usuarios y Roles', slug: 'modulos/usuarios-roles' },
					],
				},
				{
					label: 'Integración con IA',
					items: [
						{ label: 'Google Gemini', slug: 'ia/google-gemini' },
						{ label: 'Generación de Preguntas', slug: 'ia/generacion-preguntas' },
						{ label: 'Análisis de Sanciones', slug: 'ia/analisis-sanciones' },
						{ label: 'Trazabilidad', slug: 'ia/trazabilidad' },
					],
				},
				{
					label: 'API y Endpoints',
					items: [
						{ label: 'Rutas Públicas', slug: 'api/rutas-publicas' },
						{ label: 'Rutas Protegidas', slug: 'api/rutas-protegidas' },
					],
				},
				{
					label: 'Manuales de Usuario',
					items: [
						{ label: 'Manual Administrador', slug: 'manuales/administrador' },
						{ label: 'Manual Abogado', slug: 'manuales/abogado' },
						{ label: 'Manual Cliente', slug: 'manuales/cliente' },
					],
				},
				{
					label: 'Referencia',
					items: [
						{ label: 'Variables de Entorno', slug: 'referencia/variables-entorno' },
						{ label: 'Comandos Artisan', slug: 'referencia/comandos-artisan' },
						{ label: 'Troubleshooting', slug: 'referencia/troubleshooting' },
						{ label: 'Changelog', slug: 'referencia/changelog' },
					],
				},
			],
		}),
	],
});