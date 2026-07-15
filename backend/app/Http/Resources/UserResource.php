<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * No `data` envelope: `/api/user` returns this resource directly, and the
     * SPA's fetchUser() reads the bare object (user.value = data). Wrapping
     * would nest it under `data` and break that. `/api/login` nests it under
     * `user` itself, so it's unaffected either way.
     */
    public static $wrap = null;

    /**
     * Shared shape for "who is the current user" responses (`/api/login` and
     * `/api/user`) so both stay in sync instead of drifting independently.
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'permissions' => $this->resource->getAllPermissions()->pluck('name'),
            // Frontend gating for rep-only screens (Weekly Plan): admin holds
            // visits.* like every permission, so a permission check alone
            // can't distinguish "genuinely a field rep" from "admin browsing
            // the feature" -- same reasoning as VisitPlanService's role guard.
            'is_sales_representative' => $this->resource->hasRole('sales_representative'),
        ];
    }
}
