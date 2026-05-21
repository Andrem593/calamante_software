<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount('orders');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Form', [
            'roles' => $this->availableRoles(request()->user()),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'role' => 'nullable|string|in:superadmin,admin,supervisor,seller',
        ]);

        // Solo superadmin puede crear otro superadmin
        $role = $request->role ?? 'seller';
        if ($role === 'superadmin' && $request->user()->role !== 'superadmin') {
            $role = 'admin';
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => $user,
            'roles' => $this->availableRoles(request()->user()),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'nullable|string|in:superadmin,admin,supervisor,seller',
        ]);

        $data = $request->only('name', 'email', 'role');
        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        // Proteger al superadmin de eliminación
        if ($user->role === 'superadmin') {
            return back()->with('error', 'No se puede eliminar al superadministrador.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * Roles disponibles según el nivel del usuario logueado.
     */
    private function availableRoles(User $authUser): array
    {
        $all = [
            'superadmin' => 'Super Administrador',
            'admin'      => 'Administrador',
            'supervisor' => 'Supervisor',
            'seller'     => 'Vendedor',
        ];

        // Solo superadmin puede asignar el rol superadmin
        if ($authUser->role !== 'superadmin') {
            unset($all['superadmin']);
        }

        return $all;
    }
}
