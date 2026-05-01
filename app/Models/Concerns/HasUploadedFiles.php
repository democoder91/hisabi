<?php

namespace App\Models\Concerns;

use App\Models\UploadedFile;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasUploadedFiles
{
    public function uploadedFiles(): MorphMany
    {
        return $this->morphMany(UploadedFile::class, 'attachable');
    }
}