<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    use HasFactory, HasPublicUuid;

    // uuid is set by HasPublicUuid; created_by/converted_customer_id are set by
    // the controller/convert flow, never mass-assigned from request input.
    protected $fillable = [
        'name',
        'phone',
        'notes',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedCustomer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    /**
     * Row-level read scoping: admins see every prospect; a rep sees only the
     * prospects they created. Mirrors Customer::scopeVisibleTo, minus coverage
     * (which is a customer-ownership concept, not a prospect one).
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }
}
