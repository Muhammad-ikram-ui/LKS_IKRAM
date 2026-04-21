<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class Administrators extends Controller
{
    public function index (Request $request) {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $admins = User::where('role', 'admin')->get();

        return response()->json([
            'totalElements' => $admins->count(),
            'content' => $admins->map(function($admin) {
                return [
                    'username' => $admin->username,
                    'last_login_at' => $admin->last_login_at ? $admin->last_login_at->format('Y-m-d H:i:s') : '',
                    'created_at' => $admin->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $admin->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        ], 200);
    }

}
