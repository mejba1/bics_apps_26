<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MenuItem extends Model
{
    protected $fillable = ['parent_id', 'title', 'url', 'sort', 'is_active', 'new_tab'];

    protected $casts = [
        'is_active' => 'boolean',
        'new_tab' => 'boolean',
    ];

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Full link for the item. Site paths ("gallery") go through url() so they work
     * both on localhost/bics and on the live domain; "#..." and absolute URLs are kept as is.
     */
    public function getHrefAttribute(): string
    {
        $url = trim((string) $this->url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(https?:|mailto:|tel:)#i', $url)) {
            return $url === '' ? '#' : $url;
        }
        return url($url);
    }

    /** True when the item points to the page being viewed. */
    public function getIsCurrentAttribute(): bool
    {
        $url = trim((string) $this->url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(https?:|mailto:|tel:)#i', $url)) {
            return false;
        }
        $path = trim($url, '/');
        return request()->is($path === '' ? '/' : $path);
    }

    /**
     * Active top-level items with their active children, for the frontend header.
     * Falls back to the built-in menu if the table does not exist yet (e.g. before migrating).
     */
    public static function tree(): Collection
    {
        try {
            return static::whereNull('parent_id')->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort')->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect(static::defaults())->map(function ($item) {
                $parent = new static(['title' => $item['title'], 'url' => $item['url']]);
                $parent->setRelation('children', collect($item['children'])->map(fn ($c) => new static($c)));
                return $parent;
            });
        }
    }

    /** The menu the site shipped with. Used to seed the table and as a fallback. */
    public static function defaults(): array
    {
        $sub = fn (array $titles) => array_map(fn ($t) => ['title' => $t, 'url' => '#'], $titles);

        return [
            ['title' => 'Home', 'url' => '/', 'children' => []],
            ['title' => 'BICS Overview', 'url' => 'bics-overview', 'children' => $sub([
                'What is BICS', 'Objective of BICS', 'Scientific Programs', 'BICS Administration', 'Accredition',
                'Future Meeting Dates', 'Membership', 'Collaborate with BICSA', 'Contact',
            ])],
            ['title' => 'Upcoming BICS', 'url' => 'upcomming-bics', 'children' => array_merge(
                [['title' => 'Meeting Goals', 'url' => '#menu-2']],
                $sub(['Target Audience', 'Meeting Location', 'Call for Abstracts', 'Premiminary Schedule', 'Executive Comitee',
                    'Program Planing Commitee', 'Patient Advocate', 'Awards', 'Certificate', 'Attendence', 'Accredation'])
            )],
            ['title' => 'Library/ Research', 'url' => 'library-research', 'children' => []],
            ['title' => 'Contact', 'url' => 'contact-us', 'children' => []],
            ['title' => 'Gallery', 'url' => 'gallery', 'children' => []],
            ['title' => 'Media', 'url' => 'media', 'children' => []],
        ];
    }
}
