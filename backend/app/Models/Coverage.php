<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coverage extends Model
{
    use HasFactory;

    protected $fillable = [
        'representative_id',
        'covering_representative_id',
        'customer_id',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** The rep being covered (keeps ownership of their customers). */
    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    /** The stand-in temporarily granted access. */
    public function coveringRepresentative()
    {
        return $this->belongsTo(User::class, 'covering_representative_id');
    }

    /** Null customer = whole-book cover; set = one-customer cover. */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Grants that are in effect today. */
    public function scopeActive($query)
    {
        return $query->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today());
    }
}
