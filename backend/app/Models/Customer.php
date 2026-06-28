<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'customer_code',
        'year',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'is_active',
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

    public function import()
    {
        return $this->belongsTo(Import::class);
    }
}
