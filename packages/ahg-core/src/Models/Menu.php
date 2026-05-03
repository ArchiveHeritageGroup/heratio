<?php

namespace AhgCore\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';
    public $timestamps = true;

    protected $fillable = [
        'parent_id', 'name', 'path', 'lft', 'rgt', 'source_culture', 'serial_number',
    ];

    public function i18n()
    {
        return $this->hasMany(MenuI18n::class, 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the menu label for a given culture.
     *
     * Two call shapes (kept for AtoM-port compatibility):
     *   - getLabel('af')                                  → string|null (af row only)
     *   - getLabel(['cultureFallback' => true])           → string|null (current locale, fall back to en, then name)
     */
    public function getLabel(string|array $culture = 'en'): ?string
    {
        if (is_array($culture)) {
            $cur = (string) app()->getLocale();
            $fb  = (string) config('app.fallback_locale', 'en');
            $label = $this->i18n()->where('culture', $cur)->first()?->label;
            if ($label !== null && $label !== '') return $label;
            if ($cur !== $fb) {
                $label = $this->i18n()->where('culture', $fb)->first()?->label;
                if ($label !== null && $label !== '') return $label;
            }
            return $this->name;
        }
        return $this->i18n()->where('culture', $culture)->first()?->label;
    }

    /** AtoM-port helper: hasChildren */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /** AtoM-port helper: getChildren */
    public function getChildren()
    {
        return $this->children()->orderBy('lft')->get();
    }

    /** AtoM-port helper: getPath. The current schema stores plain strings on
     *  menu.path; the array shape (['getUrl' => true, 'resolveAlias' => true])
     *  is accepted for compatibility but ignored — no symfony-style aliases. */
    public function getPath($options = []): string
    {
        $path = (string) $this->path;
        if ($path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return $path;
    }
}
