<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;


class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'empresa_id',
        'bufete_id',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! in_array($this->role, ['super_admin', 'abogado', 'cliente', 'bufete'], true)) {
            return false;
        }

        // Usuario desactivado individualmente (independiente de su empresa).
        if (! $this->active) {
            return false;
        }

        // Empresa del usuario desactivada: sus usuarios quedan sin acceso al panel.
        if ($this->empresa_id && $this->empresa?->active === false) {
            return false;
        }

        return true;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function bufete(): BelongsTo
    {
        return $this->belongsTo(Bufete::class);
    }

    public function esAbogadoDeBufete(): bool
    {
        return $this->role === 'bufete' && $this->bufete_id !== null;
    }

    /** Bufete que todavía no tiene ninguna empresa: solo puede crear empresa. */
    public function bufeteNecesitaEmpresa(): bool
    {
        return $this->esAbogadoDeBufete()
            && ! \App\Models\Empresa::query()->withoutGlobalScope('bufeteOrEmpresa')
                ->where('bufete_id', $this->bufete_id)->exists();
    }

    /**
     * Bufete sin una empresa ESPECÍFICA activa en el topbar (o sin empresas).
     * Mientras esto sea true, se ocultan trabajadores/descargos/auditoría para no
     * confundir: primero debe crear/seleccionar una empresa (no "Todas").
     */
    public function bufeteSinEmpresaActiva(): bool
    {
        return $this->esAbogadoDeBufete() && \App\Support\EmpresaActiva::id() === null;
    }

    public function empresasGestionadas(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\Empresa::query()
            ->withoutGlobalScope('bufeteOrEmpresa')
            ->when(
                $this->esAbogadoDeBufete(),
                fn ($q) => $q->where('bufete_id', $this->bufete_id)
            );
    }

    /**
     * ¿Puede generar/auditar RIT (acción jurídica pesada)?
     * - super_admin / abogado: sí.
     * - cliente: solo si su empresa NO está gestionada por un bufete (si un bufete
     *   la gestiona, el RIT lo controla el bufete). Emitir sanción NO se restringe aquí.
     */
    public function puedeGestionarRit(): bool
    {
        if (in_array($this->role, ['super_admin', 'abogado', 'bufete'], true)) {
            return true;
        }
        if ($this->role === 'cliente') {
            return $this->empresa && $this->empresa->bufete_id === null;
        }
        return false;
    }

    public function procesosDisciplinariosAsignados()
    {
        return $this->hasMany(ProcesoDisciplinario::class, 'abogado_id');
    }

    public function solicitudesContratoAsignadas()
    {
        return $this->hasMany(SolicitudContrato::class, 'abogado_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAbogado(): bool
    {
        return $this->role === 'abogado';
    }

    public function isCliente(): bool
    {
        return $this->role === 'cliente';
    }

    /**
     * @deprecated El rol rrhh se unificó con cliente. Use isCliente() en su lugar.
     */
    public function isRRHH(): bool
    {
        return $this->isCliente(); // Ahora RRHH = Cliente
    }
}
