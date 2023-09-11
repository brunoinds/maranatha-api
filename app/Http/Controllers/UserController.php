<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\ListUsersRequest;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListUsersRequest $request)
    {
        return User::all();
    }

    public function show(User $user)
    {
        return response()->json($user->roles());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        if ($user->username === 'admin'){
            $role = Role::findOrCreate('admin', 'sanctum');   
            $permission = Permission::findOrCreate('add roles', 'sanctum');         
            $role->givePermissionTo($permission);
            $user->roles()->attach($role);
        }
        return response()->json(['message' => 'User created', 'user' => $user->toArray()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->tokens()->delete();
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }


    public function addRole(Request $request, User $user)
    {
        $role = Role::findOrCreate('admin', 'sanctum');
        $permission = Permission::findOrCreate('add roles', 'sanctum');
        
        $role->givePermissionTo($permission);
        $user->roles()->attach($role);
        return response()->json(['message' => 'Role added']);
    }
    public function removeRole(Request $request, User $user)
    {
        //Check if the authenticated user has the permission to remove roles:
        if (!auth()->user()->hasPermissionTo('add roles')) {
            return response()->json(['message' => 'You do not have permission to remove roles'], 403);
        }

        $user->roles()->detach($request->role_id);
        return response()->json(['message' => 'Role removed']);
    }
    public function hasRole(Request $request, User $user)
    {
        //Check if the authenticated user has the permission to remove roles:
        if (!auth()->user()->hasPermissionTo('add roles')) {
            return response()->json(['message' => 'You do not have permission to see roles'], 403);
        }

        if ($user->roles()->hasRole($request->role_id)){
            return response()->json(true, 200);
        }else{
            return response()->json(false, 404);
        }
    }

    public function roles(User $user)
    {
        return response()->json($user->roles()->get());
    }
}
