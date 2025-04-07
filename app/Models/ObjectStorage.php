<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObjectStorage extends Model
{
    protected $table = 'objects';
    
    use HasFactory, SoftDeletes;

    protected $fillable = ['bucket_id', 'key', 'path', 'version', 'visibility', 'size', 'mime_type', 'etag', 'version_id', 'locked_until'];

    protected $hidden = [
        'delete_marker', // Hanya untuk versioning
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function bucket()
    {
        return $this->belongsTo(Bucket::class);
    }
}

