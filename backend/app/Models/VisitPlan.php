<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitPlan extends Model
{
    use HasFactory, HasPublicUuid;

    // representative_id / week_start_date ARE mass-assigned, but only by
    // VisitPlanService::getOrCreateWeek's firstOrCreate() -- representative_id
    // comes from the authenticated user and week_start_date is computed
    // server-side, never raw request input, so this is safe.
    protected $fillable = [
        'representative_id',
        'week_start_date',
    ];

    protected $casts = [
        'week_start_date' => 'date',
    ];

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function entries()
    {
        return $this->hasMany(VisitPlanEntry::class);
    }

    /**
     * Row-level read scoping: admins see every rep's plan; a rep sees only
     * their own. Mirrors Prospect::scopeVisibleTo.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('representative_id', $user->id);
    }
}
