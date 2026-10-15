<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A value in one of the registration form drop-downs (Admin > Form Options).
 */
class FormOption extends Model
{
    /** The drop-downs that can be managed: key => [name, help]. */
    public const LISTS = [
        'title' => ['Titles', 'Dr, Prof, Mr...'],
        'profession' => ['Professions & specialities', 'Each profession can have its own specialities.'],
        'id_type' => ['ID types', 'Passport, national ID...'],
        'country' => ['Countries', 'Name and phone dial code.'],
    ];

    protected $fillable = ['list', 'parent_id', 'label', 'extra', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function children()
    {
        return $this->hasMany(FormOption::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(FormOption::class, 'parent_id');
    }

    /** Active values of a list, in order (empty if the table does not exist yet). */
    public static function active(string $list): Collection
    {
        try {
            return static::where('list', $list)->whereNull('parent_id')->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Active professions with their active specialities. */
    public static function professions(): Collection
    {
        try {
            return static::where('list', 'profession')->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort')->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public static function tableReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('form_options');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
