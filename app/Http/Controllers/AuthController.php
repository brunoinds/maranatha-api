<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;


class AuthController extends Controller{

    public function login(LoginUserRequest $request){
        $request->validated($request->all());

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }


        $user = User::where('username', $request->username)->first();

        return response()->json([
            'message' => 'Successfully logged in!',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()
            ],
            'token' => $user->createToken('authToken')->plainTextToken
        ], 201);
    }
    public function register(StoreUserRequest $request){
        $request->validated($request->all());
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password'=> Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Successfully created user!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ],
            'token' => $user->createToken('authToken')->plainTextToken
        ], 201);
    }
    public function logout(){
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out!'
        ], 201);
    }
}