<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Rol "bufete" (firma de abogados que gestiona empresas). Whitelist de permisos:
 * solo Empresa, Trabajador, Proceso Disciplinario, Diligencia de Descargos y
 * Reglamento Interno, más las páginas de RIT/auditoría. Todo lo demás (usuarios,
 * gestión jurídica, contratos, informes, configuración, comunicaciones) queda
 * oculto porque Shield no le concede esos permisos.
 *
 * El bufete gestiona el RIT del cliente, pero NO decide sus procesos disciplinarios:
 * puede ver/actualizar/eliminar procesos existentes (auditoría, seguimiento), pero
 * no crear uno nuevo - eso es responsabilidad del propio cliente (rol 'cliente'),
 * dueño de la decisión disciplinaria sobre sus trabajadores.
 */
class BufeteRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'bufete', 'guard_name' => 'web']);

        $recursos = ['empresa', 'trabajador', 'proceso::disciplinario', 'reglamento::interno'];
        $acciones = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

        // Permisos que NUNCA se le conceden al bufete, aunque el resto del recurso sí.
        $excluidos = ['create_proceso::disciplinario'];

        $permisos = [];
        foreach ($recursos as $r) {
            foreach ($acciones as $a) {
                $permiso = "{$a}_{$r}";
                if (in_array($permiso, $excluidos, true)) {
                    continue;
                }
                $permisos[] = $permiso;
            }
        }

        // Páginas permitidas
        $permisos = array_merge($permisos, [
            'page_MiReglamentoInterno',
            'page_AuditarRIT',
            'page_CambiarPassword',
            'page_RITBuilder',
        ]);

        $existentes = Permission::whereIn('name', $permisos)->get();
        $role->syncPermissions($existentes);

        $this->command?->info("Rol 'bufete' con {$existentes->count()} permisos.");
    }
}
