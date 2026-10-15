<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    /** Slug of the hidden page that holds site-wide sections (footer, event sidebar). */
    public const GLOBAL_SLUG = '_global';

    protected $fillable = ['slug', 'title', 'meta_title', 'meta_description', 'is_active', 'is_system'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('sort')->orderBy('id');
    }

    public function activeSections()
    {
        return $this->sections()->where('is_active', true);
    }

    public function getIsGlobalAttribute(): bool
    {
        return $this->slug === self::GLOBAL_SLUG;
    }

    /** Public address of the page. */
    public function getUrlAttribute(): string
    {
        return $this->slug === 'home' ? url('/') : url($this->slug);
    }

    /**
     * An active page with its visible sections, or null when it does not exist
     * (or the pages table has not been created yet).
     */
    public static function findForDisplay(string $slug): ?self
    {
        try {
            return static::where('slug', $slug)->where('is_active', true)->with('activeSections')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** True once the pages migration has run. */
    public static function tableReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('pages');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Content of a site-wide section (e.g. "footer"), or null if not available / hidden.
     */
    public static function globalContent(string $type): ?array
    {
        // Loaded once per request (footer and sidebar both use it)
        $attributes = request()->attributes;
        if (!$attributes->has('cms.global')) {
            try {
                $loaded = PageSection::whereHas('page', fn ($q) => $q->where('slug', self::GLOBAL_SLUG))
                    ->where('is_active', true)->get()->keyBy('type');
            } catch (\Throwable $e) {
                $loaded = collect();
            }
            $attributes->set('cms.global', $loaded);
        }
        $sections = $attributes->get('cms.global');
        return isset($sections[$type]) ? $sections[$type]->content : null;
    }
}
