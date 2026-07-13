<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitPlanEntry extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    // visit_plan_id / customer_id / planned_date / deleted_by are all set
    // explicitly by VisitPlanService, never mass-assigned.
    protected $fillable = [];

    protected $casts = [
        'planned_date' => 'date',
    ];

    public function visitPlan()
    {
        return $this->belongsTo(VisitPlan::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Row-level read/write scoping via the owning plan's rep. An entry has no
     * representative_id of its own -- ownership is "whoever owns the plan
     * this entry belongs to."
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->whereHas('visitPlan', function ($query) use ($user) {
            $query->where('representative_id', $user->id);
        });
    }
}
