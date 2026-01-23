<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        return in_array($this->role, ['super_admin', 'abogado', 'cliente']);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
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
