<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['username', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens,HasFactory, Notifiable;

    protected $fillable = [
    'username',
    'password',
    'role',
    'last_login_at'
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function authoredGames(): HasMany
    {
        return $this->hasMany(Game::class, 'created_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function highscores()
    {
        return $this->scores()->with('game')->orderBy('score', 'desc');
    }
}
