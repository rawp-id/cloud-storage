<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bucket extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'storage_path', 'access_key', 'secret_key', 'visibility', 'versioning', 'object_lock'];

    protected $hidden = ['secret_key'];

    public function objects()
    {
        return $this->hasMany(ObjectStorage::class);
    }
}
