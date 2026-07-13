<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory, HasPublicUuid;

    // customer_id / representative_id / started_at / ended_at are set by the
    // start/finish flow, not mass-assigned from request input; only notes is
    // user content.
    protected $fillable = [
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Row-level read scoping: admins see every visit; a rep sees only their own.
     * Mirrors Prospect::scopeVisibleTo (keyed on the visiting rep).
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('representative_id', $user->id);
    }
}
