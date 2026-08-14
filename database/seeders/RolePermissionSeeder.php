<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $permissions = [
            // Empresa permissions
            'view_empresa',
            'view_any_empresa',
            'create_empresa',
            'update_empresa',
            'delete_empresa',
            'delete_any_empresa',
            'force_delete_empresa',
            'force_delete_any_empresa',

            // User permissions
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
            'force_delete_user',
            'force_delete_any_user',

            // Trabajador permissions
            'view_trabajador',
            'view_any_trabajador',
            'create_trabajador',
            'update_trabajador',
            'delete_trabajador',
            'delete_any_trabajador',
            'force_delete_trabajador',
            'force_delete_any_trabajador',

            // ProcesoDisciplinario permissions
            'view_proceso::disciplinario',
            'view_any_proceso::disciplinario',
            'create_proceso::disciplinario',
            'update_proceso::disciplinario',
            'delete_proceso::disciplinario',
            'delete_any_proceso::disciplinario',
            'force_delete_proceso::disciplinario',
            'force_delete_any_proceso::disciplinario',

            // SolicitudContrato permissions
            'view_solicitud::contrato',
            'view_any_solicitud::contrato',
            'create_solicitud::contrato',
            'update_solicitud::contrato',
            'delete_solicitud::contrato',
            'delete_any_solicitud::contrato',
            'force_delete_solicitud::contrato',
            'force_delete_any_solicitud::contrato',

            // ReglamentoInterno (RIT) permissions
            'view_reglamento::interno',
            'view_any_reglamento::interno',
            'create_reglamento::interno',
            'update_reglamento::interno',
            'delete_reglamento::interno',
            'delete_any_reglamento::interno',
            'force_delete_reglamento::interno',
            'force_delete_any_reglamento::interno',
            // Página "Mi Reglamento Interno"
            'page_MiReglamentoInterno',
            // Página "Sanciones Emitidas"
            'page_SancionesEmitidas',

            // Role permissions (only for super_admin)
            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            'delete_any_role',

            // Widget permissions
            'widget_StatsOverviewWidget',
            'widget_RecentProcessesWidget',
            'widget_ExpiringTermsWidget',
            'widget_ProcessesByStatusChart',

            // Solicitudes de cambio de empresa (solo super_admin las gestiona)
            'view_any_solicitud::cambio::empresa',
            'view_solicitud::cambio::empresa',
            'update_solicitud::cambio::empresa',

            // ContratoLaboral permissions (Lupe Organiza)
            'view_contrato::laboral',
            'view_any_contrato::laboral',
            'create_contrato::laboral',
            'update_contrato::laboral',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ==============================
        // ROLE: super_admin
        // ==============================
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        // Super admin gets ALL permissions
        $superAdmin->syncPermissions(Permission::all());

        // ==============================
        // ROLE: abogado (Lawyer)
        // ==============================
        $abogado = Role::firstOrCreate(['name' => 'abogado', 'guard_name' => 'web']);
        $abogado->syncPermissions([
            // Can view empresas (but not create/edit/delete)
            'view_empresa',
            'view_any_empresa',

            // Can view users (but not manage them)
            'view_user',
            'view_any_user',

            // Full access to trabajadores
            'view_trabajador',
            'view_any_trabajador',
            'create_trabajador',
            'update_trabajador',
            'delete_trabajador',
            'delete_any_trabajador',

            // Full access to procesos disciplinarios
            'view_proceso::disciplinario',
            'view_any_proceso::disciplinario',
            'create_proceso::disciplinario',
            'update_proceso::disciplinario',
            'delete_proceso::disciplinario',
            'delete_any_proceso::disciplinario',

            // Full access to solicitudes de contrato
            'view_solicitud::contrato',
            'view_any_solicitud::contrato',
            'create_solicitud::contrato',
            'update_solicitud::contrato',
            'delete_solicitud::contrato',
            'delete_any_solicitud::contrato',

            // Reglamento Interno: puede construir/ver/editar el RIT de la empresa
            // seleccionada (opera con el selector de empresa del topbar).
            'view_reglamento::interno',
            'view_any_reglamento::interno',
            'create_reglamento::interno',
            'update_reglamento::interno',
            'page_MiReglamentoInterno',

            // Access to widgets
            'widget_StatsOverviewWidget',
            'widget_RecentProcessesWidget',
            'widget_ExpiringTermsWidget',
            'widget_ProcessesByStatusChart',

            // Lupe Organiza: contratos laborales
            'view_contrato::laboral',
            'view_any_contrato::laboral',
            'create_contrato::laboral',
            'update_contrato::laboral',
        ]);

        // ==============================
        // ROLE: cliente (Client)
        // ==============================
        $cliente = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $cliente->syncPermissions([
            // Can view trabajadores (of their empresa)
            'view_trabajador',
            'view_any_trabajador',

            // Can view AND edit their own empresa (restringido a la propia en la policy).
            'view_empresa',
            'view_any_empresa',
            'update_empresa',

            // Can view users
            'view_user',
            'view_any_user',

            // Full access to trabajadores (main responsibility)
            'create_trabajador',
            'update_trabajador',
            'delete_trabajador',
            'delete_any_trabajador',

            // Can VIEW AND CREATE disciplinary processes (Crear Citación de Descargos)
            'view_proceso::disciplinario',
            'view_any_proceso::disciplinario',
            'create_proceso::disciplinario',

            // Can VIEW AND CREATE contract requests (initiates the process)
            'view_solicitud::contrato',
            'view_any_solicitud::contrato',
            'create_solicitud::contrato',
            'update_solicitud::contrato',

            // Reglamento Interno: construir/ver/editar el suyo (restringido a su
            // empresa en ReglamentoInternoPolicy). Sin borrar.
            'view_reglamento::interno',
            'view_any_reglamento::interno',
            'create_reglamento::interno',
            'update_reglamento::interno',
            'page_MiReglamentoInterno',

            // Reporte "Sanciones Emitidas" (ver App\Filament\Admin\Pages\SancionesEmitidas)
            'page_SancionesEmitidas',

            // Access to widgets
            'widget_StatsOverviewWidget',
            'widget_RecentProcessesWidget',
            'widget_ProcessesByStatusChart',
        ]);

        $this->command->info('Roles and permissions created successfully!');
        $this->command->table(
            ['Role', 'Permissions Count'],
            [
                ['super_admin', $superAdmin->permissions->count()],
                ['abogado', $abogado->permissions->count()],
                ['cliente', $cliente->permissions->count()],
            ]
        );
    }
}
