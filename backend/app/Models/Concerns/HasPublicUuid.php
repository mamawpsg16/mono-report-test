<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a public `uuid` used in URLs/route binding, while the integer
 * `id` stays the internal PK/FK. The DB column also has a default, so rows
 * created outside Eloquent (e.g. the Python importer) still get a uuid.
 */
trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
