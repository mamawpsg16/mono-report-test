<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'processed_rows',
        'error_message',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
