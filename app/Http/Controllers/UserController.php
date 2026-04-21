<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function show($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        $currentUser = auth()->user();
        $isOwnProfile = $currentUser && $currentUser->id === $user->id;

        $authoredGames = $user->authoredGames()
            ->with('versions')
            ->get()
            ->filter(function($game) use ($isOwnProfile) {
                return $isOwnProfile || $game->versions->count() > 0;
            })
            ->map(function($game) {
                return [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description,
                ];
            })
            ->values();

        $allScores = $user->scores()
            ->with('game')
            ->get()
            ->groupBy('game_id')
            ->map(function($scores) {
                $maxScore = $scores->max('score');
                $scoreRecord = $scores->where('score', $maxScore)->first();
                return [
                    'game' => [
                        'slug' => $scoreRecord->game->slug,
                        'title' => $scoreRecord->game->title,
                        'description' => $scoreRecord->game->description,
                    ],
                    'score' => (int) $maxScore,
                    'timestamp' => $scoreRecord->created_at->toISOString(),
                ];
            })
            ->values()
            ->sortByDesc('score')
            ->values();

        return response()->json([
            'username' => $user->username,
            'registeredTimestamp' => $user->created_at->toISOString(),
            'authoredGames' => $authoredGames,
            'highscores' => $allScores,
        ]);
    }

    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $users = User::where('role', 'player')->get();

        return response()->json([
            'totalElements' => $users->count(),
            'content' => $users->map(function($user) {
                return [
                    'username' => $user->username,
                    'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : '',
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        ], 200);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $request->validate([
            'username' => 'required|min:4|max:60',
            'password' => 'required|min:5|max:10',
        ]);

        if (User::where('username', $request->username)->exists()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Username already exists'
            ], 400);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => 'player',
        ]);

        return response()->json([
            'status' => 'success',
            'username' => $user->username
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User Not found'
            ], 404);
        }

        $request->validate([
            'username' => 'required|min:4|max:60',
            'password' => 'required|min:5|max:10',
        ]);

        if (User::where('username', $request->username)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Username already exists'
            ], 400);
        }

        $user->update([
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => 'success',
            'username' => $user->username
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User Not found'
            ], 403);
        }

        $user->delete();

        return response()->json([], 204);
    }
}
