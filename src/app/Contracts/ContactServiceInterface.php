<?php

namespace App\Contracts;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;

    public function create(array $data): Contact;

    public function update(Contact $contact, array $data): Contact;

    public function delete(Contact $contact): void;
}
