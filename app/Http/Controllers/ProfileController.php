<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class ProfileController extends Controller
{
    public function showMe()
    {
        $user = auth()->user();
        return response()->json($user->toArray());
    }
}
