<?php

namespace App\Models\Lookup;

use Illuminate\Database\Eloquent\Model;

abstract class BaseLookup extends Model
{
    protected $fillable = [
        'key',
        'name_en',
        'name_tr',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the localized name based on current app locale.
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        $column = "name_{$locale}";

        return $this->{$column} ?? $this->name_en;
    }

    /**
     * Scope to only active records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Find a lookup record by its key.
     */
    public static function findByKey(string $key): ?static
    {
        return static::where('key', $key)->first();
    }

    /**
     * Find a lookup record by its key or fail.
     */
    public static function findByKeyOrFail(string $key): static
    {
        return static::where('key', $key)->firstOrFail();
    }

    /**
     * Get the ID for a given key. Cached per request.
     */
    public static function getIdByKey(string $key): ?int
    {
        $record = static::findByKey($key);

        return $record?->id;
    }
}
