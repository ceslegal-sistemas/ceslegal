# Bufetes (multi-tenant) - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que un **bufete de abogados** (firma con varios abogados) gestione un conjunto de empresas, coexistiendo con el acceso del cliente (RRHH), eligiendo empresa vs bufete en el registro.

**Architecture:** Modelo `Bufete` + `bufete_id` en `empresas` y `users` + un **global scope** de Eloquent que filtra por rol (cliente→su empresa, abogado-bufete→empresas del bufete acotadas por un selector de sesión, super_admin/abogado-interno→todo). Sin Filament tenancy nativo. Todo aditivo y nullable → convive con el flujo actual.

**Tech Stack:** Laravel 12, Filament v3, Filament Shield, PHPUnit 11, MySQL (tests en sqlite :memory:).

**Spec:** `docs/specs/2026-07-06-bufetes-multi-tenant-design.md`

**Branch:** crear `feat/bufetes` desde `v2.0` (NO desde `figma-rebrand`). Cada tarea = commit.

---

## Convenciones de este plan

- **TDD** donde hay lógica (scopes, invitaciones, permisos, registro). Filament UI se verifica manual (documentado por tarea).
- Tests en `tests/Feature/Bufete/…`. Usar `RefreshDatabase`.
- Commit al final de cada tarea. Mensajes en español, terminando con la línea `Co-Authored-By`.
- Ejecutar tests con: `php artisan test --filter <Clase>` o `vendor/bin/phpunit --filter <test>`.

---

## Task 0: Rama, DB de test y factories base

**Files:**
- Modify: `phpunit.xml` (asegurar sqlite :memory:)
- Create: `database/factories/BufeteFactory.php`
- Create: `database/factories/EmpresaFactory.php` (si no existe)
- Verify: `database/factories/UserFactory.php`

- [ ] **Step 1: Crear rama**

```bash
git fetch origin && git checkout v2.0 && git pull && git checkout -b feat/bufetes
```

- [ ] **Step 2: Asegurar DB de test sqlite in-memory** en `phpunit.xml` dentro de `<php>`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 3: EmpresaFactory** (ajustar campos a `Empresa::$fillable` real):

```php
<?php
namespace Database\Factories;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;
    public function definition(): array
    {
        return [
            'razon_social' => $this->faker->company(),
            'nit' => (string) $this->faker->unique()->numberBetween(800000000, 900000000) . '-1',
            'representante_legal' => $this->faker->name(),
            'dias_laborales' => 'lunes_viernes',
            'active' => true,
        ];
    }
}
```

- [ ] **Step 4: BufeteFactory:**

```php
<?php
namespace Database\Factories;
use App\Models\Bufete;
use Illuminate\Database\Eloquent\Factories\Factory;
class BufeteFactory extends Factory
{
    protected $model = Bufete::class;
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company() . ' Abogados',
            'nit' => (string) $this->faker->unique()->numberBetween(800000000, 900000000) . '-2',
            'representante' => $this->faker->name(),
            'email_contacto' => $this->faker->companyEmail(),
            'active' => true,
        ];
    }
}
```

- [ ] **Step 5: Añadir `HasFactory` a `Empresa` y (más adelante) `Bufete`.** Verificar que `User` tenga `role`, `empresa_id`, `bufete_id` en `$fillable` (bufete_id se agrega en Task 3).

- [ ] **Step 6: Commit** `chore(test): sqlite in-memory + factories Empresa/Bufete`.

---

## Task 1: Tabla y modelo `Bufete`

**Files:**
- Create: `database/migrations/2026_07_06_100000_create_bufetes_table.php`
- Create: `app/Models/Bufete.php`
- Test: `tests/Feature/Bufete/BufeteModelTest.php`

- [ ] **Step 1: Test (falla):**

```php
<?php
namespace Tests\Feature\Bufete;
use App\Models\Bufete;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BufeteModelTest extends TestCase
{
    use RefreshDatabase;
    public function test_bufete_tiene_muchas_empresas(): void
    {
        $bufete = Bufete::factory()->create();
        Empresa::factory()->count(2)->create(['bufete_id' => $bufete->id]);
        $this->assertCount(2, $bufete->empresas);
    }
}
```

- [ ] **Step 2: Correr y ver fallar:** `php artisan test --filter BufeteModelTest` → FAIL (clase/tabla inexistente).

- [ ] **Step 3: Migración `bufetes`:**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bufetes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('nit')->nullable()->unique();
            $t->string('representante')->nullable();
            $t->string('email_contacto')->nullable();
            $t->string('telefono', 20)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bufetes'); }
};
```

- [ ] **Step 4: Modelo `Bufete`** (la relación `empresas` compila aunque `empresas.bufete_id` se cree en Task 2; el test de esta task ya lo requiere, así que incluir la columna aquí o correr Task 2 antes - ver nota). Para mantener la task autocontenida, **incluir en esta migración** también `empresas.bufete_id` NO; en su lugar, **reordenar**: ejecutar Task 2 como parte del mismo commit lógico. Alternativa simple: crear ambas columnas aquí. Decisión: **crear `empresas.bufete_id` en Task 2 y mover este test a después de Task 2.** (Ver Task 2.)

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Bufete extends Model
{
    use HasFactory;
    protected $fillable = ['nombre','nit','representante','email_contacto','telefono','active'];
    protected $casts = ['active' => 'boolean'];
    public function empresas(): HasMany { return $this->hasMany(Empresa::class); }
    public function abogados(): HasMany { return $this->hasMany(User::class); }
}
```

- [ ] **Step 5:** correr el test tras Task 2. **Commit** `feat(bufete): tabla y modelo Bufete`.

> **Nota de orden:** Task 1 y Task 2 están acopladas (la relación `empresas()` necesita `empresas.bufete_id`). Ejecutarlas juntas o en el orden 1→2 y correr `BufeteModelTest` al terminar Task 2.

---

## Task 2: `empresas.bufete_id` + relación

**Files:**
- Create: `database/migrations/2026_07_06_100100_add_bufete_id_to_empresas_table.php`
- Modify: `app/Models/Empresa.php`
- Test: (reutiliza `BufeteModelTest`)

- [ ] **Step 1: Migración:**

```php
Schema::table('empresas', function (Blueprint $t) {
    $t->foreignId('bufete_id')->nullable()->after('id')
      ->constrained('bufetes')->nullOnDelete();
});
```
(con `down()` que haga `dropConstrainedForeignId('bufete_id')`).

- [ ] **Step 2: Relación en `Empresa`:**

```php
public function bufete(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{ return $this->belongsTo(Bufete::class); }
```
Añadir `'bufete_id'` a `Empresa::$fillable`.

- [ ] **Step 3: Correr `BufeteModelTest`** → PASS.

- [ ] **Step 4: Commit** `feat(bufete): empresas.bufete_id + relacion`.

---

## Task 3: `users.bufete_id` + helpers de rol

**Files:**
- Create: `database/migrations/2026_07_06_100200_add_bufete_id_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Bufete/UserBufeteTest.php`

- [ ] **Step 1: Test (falla):**

```php
public function test_abogado_de_bufete_se_identifica_y_lista_empresas(): void
{
    $bufete = Bufete::factory()->create();
    $abogado = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
    Empresa::factory()->count(3)->create(['bufete_id' => $bufete->id]);
    Empresa::factory()->create(); // otra, fuera del bufete
    $this->assertTrue($abogado->esAbogadoDeBufete());
    $this->assertEqualsCanonicalizing(
        $bufete->empresas->pluck('id')->all(),
        $abogado->empresasGestionadas()->pluck('id')->all()
    );
}
```

- [ ] **Step 2:** correr → FAIL.

- [ ] **Step 3: Migración** `users.bufete_id` (nullable, `constrained('bufetes')->nullOnDelete()`).

- [ ] **Step 4: `User`:** añadir `'bufete_id'` a `$fillable`, relación y helpers:

```php
public function bufete() { return $this->belongsTo(Bufete::class); }
public function esAbogadoDeBufete(): bool { return $this->role === 'abogado' && $this->bufete_id !== null; }
public function empresasGestionadas() {
    // Empresas visibles para este usuario (sin aplicar el selector).
    return Empresa::query()->when($this->esAbogadoDeBufete(),
        fn($q) => $q->where('bufete_id', $this->bufete_id));
}
```

- [ ] **Step 5:** correr → PASS. **Commit** `feat(bufete): users.bufete_id + helpers de rol`.

---

## Task 4: Global scope de acceso (núcleo)

**Files:**
- Create: `app/Models/Concerns/ScopedToBufeteOrEmpresa.php`
- Modify: `app/Models/Empresa.php`, `Trabajador.php`, `ProcesoDisciplinario.php`, `ReglamentoInterno.php`, `SolicitudContrato.php` (los que tengan `empresa_id`; verificar cada uno)
- Create: `app/Support/EmpresaActiva.php` (helper del selector de sesión)
- Test: `tests/Feature/Bufete/ScopeAccesoTest.php`

- [ ] **Step 1: Test (falla) - cubre los 4 casos:**

```php
public function test_scope_por_rol(): void
{
    $bufete = Bufete::factory()->create();
    $e1 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
    $e2 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
    $ajena = Empresa::factory()->create(); // sin bufete

    $cliente  = User::factory()->create(['role' => 'cliente', 'empresa_id' => $e1->id]);
    $abogado  = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
    $super    = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($cliente);
    $this->assertEqualsCanonicalizing([$e1->id], Empresa::pluck('id')->all());

    $this->actingAs($abogado);
    $this->assertEqualsCanonicalizing([$e1->id, $e2->id], Empresa::pluck('id')->all());

    // selector acota a una empresa
    \App\Support\EmpresaActiva::set($e2->id);
    $this->assertEqualsCanonicalizing([$e2->id], Empresa::pluck('id')->all());
    \App\Support\EmpresaActiva::clear();

    $this->actingAs($super);
    $this->assertEqualsCanonicalizing([$e1->id, $e2->id, $ajena->id], Empresa::pluck('id')->all());
}
```

- [ ] **Step 2:** correr → FAIL.

- [ ] **Step 3: Helper `EmpresaActiva`:**

```php
<?php
namespace App\Support;
class EmpresaActiva
{
    public const KEY = 'bufete_empresa_activa';
    public static function id(): ?int { return session(self::KEY) ? (int) session(self::KEY) : null; }
    public static function set(int $empresaId): void { session([self::KEY => $empresaId]); }
    public static function clear(): void { session()->forget(self::KEY); }
}
```

- [ ] **Step 4: Trait de scope** (aplica según el modelo tenga `empresa_id` o sea el propio `Empresa`):

```php
<?php
namespace App\Models\Concerns;
use App\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\Builder;
trait ScopedToBufeteOrEmpresa
{
    public static function bootScopedToBufeteOrEmpresa(): void
    {
        static::addGlobalScope('bufeteOrEmpresa', function (Builder $q) {
            $user = auth()->user();
            if (! $user) return;                                   // consola/sin auth: sin filtro
            $model = $q->getModel();
            $col = $model instanceof \App\Models\Empresa ? 'id' : 'empresa_id';

            if ($user->role === 'super_admin') return;             // todo
            if ($user->role === 'abogado' && $user->bufete_id === null) return; // staff interno: todo

            if ($user->role === 'cliente') {                       // su empresa
                $q->where($col, $user->empresa_id);
                return;
            }
            if ($user->role === 'abogado' && $user->bufete_id) {   // empresas del bufete
                $ids = \App\Models\Empresa::query()->withoutGlobalScope('bufeteOrEmpresa')
                    ->where('bufete_id', $user->bufete_id)->pluck('id')->all();
                if ($activa = EmpresaActiva::id()) {
                    $ids = in_array($activa, $ids, true) ? [$activa] : $ids;
                }
                $q->whereIn($col, $ids ?: [0]);
                return;
            }
            $q->whereRaw('1 = 0'); // rol desconocido: nada
        });
    }
}
```

- [ ] **Step 5: Aplicar el trait** a `Empresa` primero (para que el test base pase), correr → parte del test PASA. Luego aplicarlo a `Trabajador`, `ProcesoDisciplinario`, `ReglamentoInterno`, `SolicitudContrato` (verificar que cada uno tenga `empresa_id`).

> **Cuidado:** el trait consulta `Empresa::withoutGlobalScope(...)` para evitar recursión. Verificar que no rompa comandos artisan/seeders (sin `auth()`, no filtra). Revisar jobs/colas que corran como sistema.

- [ ] **Step 6:** correr `ScopeAccesoTest` → PASS. **Commit** `feat(bufete): global scope de acceso por rol + selector de sesion`.

---

## Task 5: Selector de "Empresa" en el topbar (abogado de bufete)

**Files:**
- Create: `app/Livewire/SelectorEmpresa.php` (o página Filament simple)
- Create: `resources/views/livewire/selector-empresa.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (render hook `TOPBAR_END` / `USER_MENU_BEFORE`)
- Test: `tests/Feature/Bufete/SelectorEmpresaTest.php`

- [ ] **Step 1: Test (falla):** un componente Livewire que setea `EmpresaActiva` y valida que solo permite empresas del bufete del usuario.

```php
public function test_selector_solo_acepta_empresas_del_bufete(): void
{
    $bufete = Bufete::factory()->create();
    $e = Empresa::factory()->create(['bufete_id' => $bufete->id]);
    $ajena = Empresa::factory()->create();
    $abogado = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
    $this->actingAs($abogado);

    Livewire::test(\App\Livewire\SelectorEmpresa::class)->call('seleccionar', $e->id);
    $this->assertSame($e->id, \App\Support\EmpresaActiva::id());

    Livewire::test(\App\Livewire\SelectorEmpresa::class)->call('seleccionar', $ajena->id);
    $this->assertSame($e->id, \App\Support\EmpresaActiva::id()); // no cambió: ajena rechazada
}
```

- [ ] **Step 2:** correr → FAIL.

- [ ] **Step 3: Componente Livewire `SelectorEmpresa`:** método `seleccionar($id)` que valida `in_array($id, empresasGestionadas ids)` antes de `EmpresaActiva::set`, y `todas()` que hace `EmpresaActiva::clear()`. `render()` lista las empresas del bufete + opción "Todas".

- [ ] **Step 4: Vista** - dropdown con estilo de marca (reutilizar tokens `ces-*`). Mostrar la empresa activa o "Todas".

- [ ] **Step 5: Render hook** solo visible si `auth()->user()?->esAbogadoDeBufete()`:

```php
FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_END,
    fn(): string => auth()->user()?->esAbogadoDeBufete()
        ? \Livewire\Livewire::mount(\App\Livewire\SelectorEmpresa::class)
        : '');
```
(Verificar la API correcta para renderizar Livewire en un render hook de Filament; alternativa: `Blade::render('@livewire(...)')`.)

- [ ] **Step 6:** correr → PASS. **Verificación manual:** loguear como abogado de bufete, cambiar empresa, confirmar que las tablas se acotan. **Commit** `feat(bufete): selector de empresa en topbar`.

---

## Task 6: Registro - elección Empresa vs Bufete

**Files:**
- Modify: `app/Filament/Admin/Pages/Auth/Register.php`
- Create: `resources/views/filament/components/register-tipo-cuenta.blade.php`
- Test: `tests/Feature/Bufete/RegistroBufeteTest.php`

- [ ] **Step 1: Test (falla) - registro de bufete crea Bufete + abogado:**

```php
public function test_handle_registration_bufete_crea_bufete_y_abogado(): void
{
    $page = new \App\Filament\Admin\Pages\Auth\Register();
    $user = $this->invokeHandleRegistration($page, [
        'tipo_cuenta' => 'bufete',
        'bufete_nombre' => 'Rendón & Asociados',
        'bufete_nit' => '900111222-3',
        'name' => 'Juan', 'email' => 'juan@bufete.co', 'password' => 'secret123',
    ]);
    $this->assertSame('abogado', $user->role);
    $this->assertNotNull($user->bufete_id);
    $this->assertDatabaseHas('bufetes', ['nombre' => 'Rendón & Asociados']);
}
```
(Extraer la lógica de creación a métodos testeables: `crearCuentaBufete(array $data): User` y `crearCuentaEmpresa(array $data): User`, llamados desde `handleRegistration` según `tipo_cuenta`.)

- [ ] **Step 2:** correr → FAIL.

- [ ] **Step 3:** Añadir **Paso 0 "Tipo de cuenta"** al wizard de registro: `Radio::make('tipo_cuenta')` con `empresa|bufete` (`->live()`), tarjeta explicativa. Los pasos de empresa (`->visible(fn(Get $g)=>$g('tipo_cuenta')==='empresa')`) y un paso nuevo de bufete (`->visible(...==='bufete')`) con `bufete_nombre`, `bufete_nit`, `representante`, contacto + la cuenta del abogado (name/email/password ya están en el paso "Cuenta", que se comparte).

- [ ] **Step 4:** Refactor `handleRegistration`: envolver en `DB::transaction`; si `tipo_cuenta==='bufete'` → `crearCuentaBufete` (crea `Bufete` + `User(role=abogado, bufete_id)`), redirect al dashboard; si no → flujo empresa actual (extraído a `crearCuentaEmpresa`).

- [ ] **Step 5:** correr → PASS. **Verificación manual:** registrar un bufete y una empresa; confirmar rol/redirect. **Commit** `feat(bufete): registro elige empresa vs bufete`.

---

## Task 7: Resource "Empresas" del bufete (crear nueva + acceso RRHH)

**Files:**
- Create: `app/Filament/Admin/Resources/EmpresaBufeteResource.php` (o extender el `EmpresaResource` existente con visibilidad por rol)
- Test: `tests/Feature/Bufete/CrearEmpresaBufeteTest.php`

- [ ] **Step 1: Test (falla):** al crear una empresa como abogado de bufete, `bufete_id` se asigna automáticamente al bufete del usuario; opción "acceso RRHH" crea un `User(cliente, empresa_id)`.
- [ ] **Step 2:** correr → FAIL.
- [ ] **Step 3:** Reusar `EmpresaResource` (si existe) o crear el resource. En `mutateFormDataBeforeCreate`: `$data['bufete_id'] = auth()->user()->bufete_id` cuando `esAbogadoDeBufete()`. Toggle "Dar acceso al RRHH (crea usuario cliente)" + campos email/nombre → crea `User(cliente)` en `afterCreate` (dentro de transacción).
- [ ] **Step 4:** Visibilidad: el resource "Empresas" visible solo para `abogado`/`super_admin` (Shield). El scope de Task 4 ya limita a las empresas del bufete.
- [ ] **Step 5:** correr → PASS. **Commit** `feat(bufete): alta de empresas nuevas bajo el bufete`.

---

## Task 8: Invitar empresa existente

**Files:**
- Create: `database/migrations/2026_07_06_100300_create_bufete_invitaciones_table.php`
- Create: `app/Models/BufeteInvitacion.php`
- Create: acción "Invitar empresa" + notificación/email + ruta pública de aceptación
- Create: `app/Http/Controllers/BufeteInvitacionController.php` (aceptar/rechazar por token)
- Test: `tests/Feature/Bufete/InvitacionEmpresaTest.php`

- [ ] **Step 1: Tests (fallan):**
  - Invitar por NIT genera invitación `pendiente` con token.
  - Aceptar setea `empresa.bufete_id` y marca `aceptada`.
  - Guard de **exclusividad**: si la empresa ya tiene `bufete_id`, la invitación se rechaza al crearla.
  - Token expirado → no acepta.
- [ ] **Step 2:** correr → FAIL.
- [ ] **Step 3:** Migración `bufete_invitaciones` (`bufete_id, nit, email, token unique, estado, expires_at, timestamps`). Modelo con casts y scopes `pendientes()`.
- [ ] **Step 4:** Acción "Invitar empresa" (busca `Empresa` por NIT sin bufete; crea invitación + envía email con link `route('bufete.invitacion.aceptar', $token)`). Controlador que valida token/expiración, setea `bufete_id`, marca estado.
- [ ] **Step 5:** correr → PASS. **Verificación manual** del email/enlace. **Commit** `feat(bufete): invitar y absorber empresas existentes`.

---

## Task 9: Permisos - gates RIT vs sanción + gestión de abogados

**Files:**
- Modify: `app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php` (gates)
- Modify: páginas `AuditarRIT`, `MiReglamentoInterno`, `CreateReglamentoInterno` (visibilidad generar/auditar)
- Create: recurso/gestión de abogados del bufete (invitar/crear `User(abogado, bufete_id)`)
- Modify: Shield seeders/policies para `Bufete`, `BufeteInvitacion`
- Test: `tests/Feature/Bufete/PermisosTest.php`

- [ ] **Step 1: Tests (fallan):**
  - `cliente` **puede** emitir sanción (gate existente lo permite).
  - `cliente` **no** puede generar/auditar RIT.
  - `abogado(bufete)` puede ambas sobre empresas de su bufete.
- [ ] **Step 2:** correr → FAIL.
- [ ] **Step 3:** Ajustar gates: generar/auditar RIT → `->visible(fn()=>auth()->user()?->hasAnyRole(['super_admin','abogado']))`; emitir sanción → mantener permitido a `cliente`. Añadir gestión "Abogados del bufete" (crear/invitar usuarios `abogado` con el `bufete_id` del dueño; solo dueño/admin del bufete).
- [ ] **Step 4:** Registrar `Bufete`/`BufeteInvitacion` en Shield; correr `php artisan shield:generate` si aplica.
- [ ] **Step 5:** correr → PASS. **Commit** `feat(bufete): permisos RIT/sancion + gestion de abogados`.

---

## Task 10: Integración, panel access y verificación final

**Files:**
- Modify: `app/Models/User.php` (`canAccessPanel` si aplica), `AdminPanelProvider` (navegación por rol)
- Test: `tests/Feature/Bufete/FlujoCompletoTest.php`

- [ ] **Step 1: Test de flujo (falla):** registrar bufete → crear/invitar 2 empresas → crear trabajador y proceso en una → confirmar que el abogado ve ambas empresas y el selector acota; un cliente de una empresa no ve la otra.
- [ ] **Step 2:** correr → FAIL; ajustar lo que falte.
- [ ] **Step 3:** Navegación: grupos/recursos visibles por rol (Empresas y Abogados solo para bufete/super_admin). Verificar que un abogado de bufete tenga `empresa_id` null y aún así `canAccessPanel`.
- [ ] **Step 4:** correr toda la suite `php artisan test --filter Bufete` → PASS.
- [ ] **Step 5:** **Verificación manual E2E** en local (registro bufete, alta/invitación, selector, permisos). **Commit** `feat(bufete): integracion y verificacion del flujo multi-tenant`.

---

## Riesgos / notas de despliegue

- **Global scope + jobs/comandos:** sin `auth()` no filtra (correcto para consola), pero revisar jobs que actúen "en nombre de" un usuario.
- **Backward-compat:** `bufete_id` nullable → datos actuales intactos; `abogado` interno (bufete_id null) sigue viendo todo.
- **Deploy Hostinger (v2.0):** solo migraciones, sin npm. Probar primero en `descargos.ceslegal.co`.
- **Pendientes de la sección 11 del spec** (facturación por bufete/empresa, RIT propio del bufete, notificaciones, trazabilidad de actor) NO están en este plan; se afinan aparte.

## Definición de "hecho"

- Suite `--filter Bufete` en verde.
- Registro empresa y bufete funcionan; scope y selector correctos; invitación con exclusividad; gates RIT/sanción según lo acordado.
- Sin regresiones en el flujo actual (empresas sin bufete se comportan igual).
