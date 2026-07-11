<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, HasPublicUuid;

    // `uuid` is set by HasPublicUuid / the DB default, never mass-assigned.
    protected $fillable = [
        'original_filename',
        'customer_code',
        'year',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'assigned_representative_id',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function deactivate()
    {
        return $this->update(['is_active' => false]);
    }

    public function activate()
    {
        return $this->update(['is_active' => true]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedRepresentative()
    {
        return $this->belongsTo(User::class, 'assigned_representative_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Restrict a query to the customers a user is allowed to see.
     *
     * Admins see everything. A sales rep sees a customer only if they OWN it, or
     * an active coverage grants it — either a single-customer grant, or a
     * whole-book grant over the customer's owner. This is the security spine:
     * every customer listing/lookup for a rep must go through here.
     */
    public function scopeVisibleTo($query, User $user)
    {
        // Admin bypass. NOTE: role check (not permission) is deliberate — the
        // `customers.view` permission gates the *module*; "sees everyone's rows"
        // is an ownership/scope concern. Swap to a dedicated capability later if
        // we grow more manager-like roles.
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('assigned_representative_id', $user->id)
                // single-customer coverage: this exact customer is granted
                ->orWhereIn('id', function ($sub) use ($user) {
                    $sub->select('customer_id')->from('coverages')
                        ->where('covering_representative_id', $user->id)
                        ->whereNotNull('customer_id')
                        ->whereDate('starts_on', '<=', today())
                        ->whereDate('ends_on', '>=', today());
                })
                // whole-book coverage: this customer's owner is being covered
                ->orWhereIn('assigned_representative_id', function ($sub) use ($user) {
                    $sub->select('representative_id')->from('coverages')
                        ->where('covering_representative_id', $user->id)
                        ->whereNull('customer_id')
                        ->whereDate('starts_on', '<=', today())
                        ->whereDate('ends_on', '>=', today());
                });
        });
    }
}
