<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::with('permissions')->get());
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->validated('permissions', []));

        return response()->json($role->load('permissions'), 201);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role->syncPermissions($request->validated('permissions', []));

        return response()->json($role->load('permissions'));
    }
}
