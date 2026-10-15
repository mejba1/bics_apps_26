<?php

namespace App\Models;

use App\Support\SectionTypes;
use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $fillable = ['page_id', 'type', 'label', 'content', 'sort', 'is_active'];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    /** Definition of this section's type (label, icon, fields). */
    public function getDefinitionAttribute(): array
    {
        return SectionTypes::get($this->type) ?? ['label' => $this->type, 'icon' => 'bi-square', 'fields' => []];
    }

    /** Name shown in the admin list. */
    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: $this->definition['label'];
    }
}
