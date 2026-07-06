<?php

namespace App\Support;

class EmpresaActiva
{
    public const KEY = 'bufete_empresa_activa';

    public static function id(): ?int
    {
        return session(self::KEY) ? (int) session(self::KEY) : null;
    }

    public static function set(int $empresaId): void
    {
        session([self::KEY => $empresaId]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
