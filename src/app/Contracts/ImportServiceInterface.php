<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ImportServiceInterface
{
    public function import(UploadedFile $file): array;
}
