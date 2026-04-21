<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function signup (Request $request) {
        request()->validate([
            'username'=>'required|unique:users,username|min:4|max:60',
            'password'=>'required|min:5|max:10',
        ]);

        $user = User::create([
            'username'=>$request->username,
            'password'=>Hash::make($request->password)
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'=>'success',
            'token'=>$token,
        ], 201);
    }

    public function signin(Request $request) {
        $credentials = $request->validate([
            'username'=>'required|min:4|max:60',
            'password'=>'required|min:5|max:50'
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
            'status'=>'invalid',
            'message'=>'Wrong username or password'
            ], 401);
        }
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'=>'success',
            'token'=>$token,
        ], 200);
    }

    public function signout(Request $request) {
        
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
        'status'=>'success',
        ], 201);
    }
}
