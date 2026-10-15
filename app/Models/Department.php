<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'color', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The departments every new installation starts with. */
    public const DEFAULTS = [
        ['name' => 'Executive', 'code' => 'EXEC', 'color' => 'violet'],
        ['name' => 'Human Resources', 'code' => 'HR', 'color' => 'rose'],
        ['name' => 'Leasing', 'code' => 'LSG', 'color' => 'amber'],
        ['name' => 'Marketing', 'code' => 'MKT', 'color' => 'fuchsia'],
        ['name' => 'Security', 'code' => 'SEC', 'color' => 'slate'],
        ['name' => 'Operations', 'code' => 'OPS', 'color' => 'indigo'],
        ['name' => 'Information Technology', 'code' => 'IT', 'color' => 'sky'],
        ['name' => 'Accounting', 'code' => 'ACC', 'color' => 'emerald'],
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
