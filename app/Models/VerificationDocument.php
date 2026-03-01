<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
