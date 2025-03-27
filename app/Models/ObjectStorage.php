<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjectStorage extends Model
{
    protected $table = 'objects';
    
    use HasFactory;

    protected $fillable = ['bucket_id', 'key', 'path', 'version', 'visibility'];

    public function bucket()
    {
        return $this->belongsTo(Bucket::class);
    }
}

