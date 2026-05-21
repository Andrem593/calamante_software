<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Roles que tienen acceso al panel administrativo.
     */
    const ADMIN_ROLES = ['superadmin', 'admin', 'supervisor'];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Si se especifican roles concretos, comprobar esos
        $allowedRoles = empty($roles) ? self::ADMIN_ROLES : $roles;

        if (!in_array($user->role, $allowedRoles)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
