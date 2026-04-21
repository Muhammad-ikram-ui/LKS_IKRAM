<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $page = (int) $request->query('page', 0);
        $size = max((int) $request->query('size', 10), 1);
        $sortBy = $request->query('sortBy', 'title');
        $sortDir = $request->query('sortDir', 'asc');

        if (!in_array($sortBy, ['title', 'popular', 'uploaddate'])) {
            return response()->json(['error' => 'Invalid sortBy'], 400);
        }
        if (!in_array($sortDir, ['asc', 'desc'])) {
            return response()->json(['error' => 'Invalid sortDir'], 400);
        }

        $query = Game::with(['creator', 'versions'])
            ->withCount('scores')
            ->whereHas('versions'); 

        if ($sortBy === 'title') {
            $query->orderBy('title', $sortDir);
        } elseif ($sortBy === 'popular') {
            $query->orderBy('scores_count', $sortDir);
        } elseif ($sortBy === 'uploaddate') {
            $query->orderBy(
                Game::selectRaw('MAX(uploaded_at)')
                    ->from('game_versions')
                    ->whereColumn('game_versions.game_id', 'games.id'),
                $sortDir
            );
        }

        $totalElements = $query->count();
        $games = $query->skip($page * $size)->take($size)->get();

        $content = $games->map(function ($game) {
            $latestVersion = $game->latestVersion();
            return [
                'slug' => $game->slug,
                'title' => $game->title,
                'description' => $game->description,
                'thumbnail' => $latestVersion ? ($latestVersion->thumbnail ? "/games/{$game->slug}/{$latestVersion->version}/thumbnail.png" : null) : null,
                'uploadTimestamp' => $latestVersion ? \Carbon\Carbon::parse($latestVersion->uploaded_at)->toISOString() : null,
                'author' => $game->creator->username,
                'scoreCount' => $game->scores_count,
            ];
        });

        return response()->json([
            'page' => $page,
            'size' => $size,
            'totalElements' => $totalElements,
            'content' => $content,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $violations = [];

        if (!$request->has('title')) {
            $violations['title'] = ['message' => 'required'];
        } elseif (is_string($request->title)) {
            if (strlen($request->title) < 3) {
                $violations['title'] = ['message' => 'must be at least 3 characters long'];
            } elseif (strlen($request->title) > 60) {
                $violations['title'] = ['message' => 'must be at most 60 characters long'];
            }
        }

        if (!$request->has('description')) {
            $violations['description'] = ['message' => 'required'];
        } elseif (is_string($request->description) && strlen($request->description) > 200) {
            $violations['description'] = ['message' => 'must be at most 200 characters long'];
        }

        if (!empty($violations)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid.',
                'violations' => $violations
            ], 400);
        }

        $slug = Str::slug($request->title);

        if (Game::where('slug', $slug)->exists()) {
            return response()->json([
                'status' => 'invalid',
                'slug' => 'Game title already exists'
            ], 400);
        }

        $game = Game::create([
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'slug' => $game->slug
        ], 201);
    }

    public function show($slug)
    {
        $game = Game::with(['creator', 'versions'])
            ->withCount('scores')
            ->where('slug', $slug)
            ->whereHas('versions')
            ->first();

        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        $latestVersion = $game->latestVersion();

        return response()->json([
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'thumbnail' => $latestVersion && $latestVersion->thumbnail ? "/games/{$game->slug}/{$latestVersion->version}/thumbnail.png" : null,
            'uploadTimestamp' => $latestVersion ? $latestVersion->uploaded_at->toISOString() : null,
            'author' => $game->creator->username,
            'scoreCount' => $game->scores_count,
            'gamePath' => "/games/{$game->slug}/{$latestVersion->version}/",
        ]);
    }

    public function update(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        if ($game->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $violations = [];

        if ($request->has('title')) {
            if (!$request->title) {
                $violations['title'] = ['message' => 'required'];
            } elseif (strlen($request->title) < 3) {
                $violations['title'] = ['message' => 'must be at least 3 characters long'];
            } elseif (strlen($request->title) > 60) {
                $violations['title'] = ['message' => 'must be at most 60 characters long'];
            }
        }

        if ($request->has('description')) {
            if ($request->description === null) {
                $violations['description'] = ['message' => 'required'];
            } elseif (is_string($request->description) && strlen($request->description) > 200) {
                $violations['description'] = ['message' => 'must be at most 200 characters long'];
            }
        }

        if (!empty($violations)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid.',
                'violations' => $violations
            ], 400);
        }

        if ($request->has('title')) {
            $game->title = $request->title;
        }
        if ($request->has('description')) {
            $game->description = $request->description;
        }
        $game->save();

        return response()->json([
            'status' => 'success'
        ], 200);
    }

    public function destroy($slug)
    {
        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        if ($game->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $game->delete();

        return response()->noContent();
    }

    public function upload(Request $request, $slug)
    {
        $token = $request->input('token');
        
        if (!$token) {
            return response()->plain('Missing token', 401);
        }

        $user = null;
        if ($token) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($tokenModel) {
                $user = $tokenModel->tokenable;
            }
        }

        if (!$user) {
            return response()->plain('Invalid token', 401);
        }

        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->plain('Game not found', 404);
        }

        if ($game->created_by !== $user->id) {
            return response()->plain('User is not author of the game', 403);
        }

        if (!$request->hasFile('zipfile')) {
            return response()->plain('Zip file is required', 400);
        }

        $file = $request->file('zipfile');
        $latestVersion = $game->versions()->max('version') ?? 0;
        $newVersion = $latestVersion + 1;

        $uploadDir = "games/{$slug}/{$newVersion}";
        Storage::makeDirectory($uploadDir);

        $zip = new ZipArchive();
        if ($zip->open($file->getPathname()) === TRUE) {
            $zip->extractTo(storage_path("app/{$uploadDir}"));
            $zip->close();
        } else {
            return response()->plain('Failed to extract zip file', 400);
        }

        $thumbnail = null;
        if (file_exists(storage_path("app/{$uploadDir}/thumbnail.png"))) {
            $thumbnail = 'thumbnail.png';
        }

        GameVersion::create([
            'game_id' => $game->id,
            'version' => $newVersion,
            'thumbnail' => $thumbnail,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'status' => 'success'
        ], 201);
    }

    public function getScores($slug)
    {
        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        $scores = DB::table('scores')
            ->join('users', 'scores.user_id', '=', 'users.id')
            ->where('scores.game_id', $game->id)
            ->selectRaw('users.username, MAX(scores.score) as score, MAX(scores.created_at) as timestamp')
            ->groupBy('scores.user_id', 'users.username')
            ->orderBy('score', 'desc')
            ->get();

        return response()->json([
            'scores' => $scores->map(function($score) {
                return [
                    'username' => $score->username,
                    'score' => (int) $score->score,
                    'timestamp' => \Carbon\Carbon::parse($score->timestamp)->toISOString(),
                ];
            })->toArray()
        ]);
    }

    public function postScore(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        if (!$request->has('score')) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid.',
                'violations' => [
                    'score' => ['message' => 'required']
                ]
            ], 400);
        }

        if (!is_numeric($request->score)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid.',
                'violations' => [
                    'score' => ['message' => 'must be a number']
                ]
            ], 400);
        }

        $latestVersion = $game->versions()->latest('version')->first();
        if (!$latestVersion) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        $user = Auth::user();
        if ($user->role === 'blocked') {
            return response()->json([
                'status' => 'blocked',
                'message' => 'User blocked',
                'reason' => 'You have been blocked by an administrator'
            ], 403);
        }

        Score::create([
            'game_id' => $game->id,
            'user_id' => Auth::id(),
            'score' => (int) $request->score,
        ]);

        return response()->json([
            'status' => 'success'
        ], 201);
    }

    public function serveGame($slug, $version, $path)
    {
        $filePath = "games/{$slug}/{$version}/{$path}";
        $fullPath = storage_path("app/{$filePath}");

        if (!file_exists($fullPath)) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Not found'
            ], 404);
        }

        return response()->file($fullPath);
    }
}
