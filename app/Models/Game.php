<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GameVersion::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function latestVersion()
    {
        return $this->versions()->latest('uploaded_at')->first();
    }
}
