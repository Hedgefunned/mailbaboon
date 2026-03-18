<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface ImportServiceInterface
{
    public function import(UploadedFile $file, ?callable $onProgress = null): array;

    public function listRejected(array $filters): LengthAwarePaginator;

    public function truncateContacts(): void;
}
