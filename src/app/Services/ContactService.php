<?php

namespace App\Services;

use App\Contracts\ContactServiceInterface;
use App\Models\Contact;
use Illuminate\Support\Collection;

class ContactService implements ContactServiceInterface
{
    public function list(array $filters): Collection
    {
        return Contact::orderBy('last_name')->orderBy('first_name')->get();
    }

    public function create(array $data): Contact
    {
        return Contact::create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact->fresh();
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }
}
