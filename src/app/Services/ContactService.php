<?php

namespace App\Services;

use App\Contracts\ContactServiceInterface;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactService implements ContactServiceInterface
{
    private const PER_PAGE = 25;

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Contact::orderBy('last_name')->orderBy('first_name');

        if ($search = trim($filters['search'] ?? '')) {
            $terms = collect(preg_split('/\s+/', $search))
                ->filter()
                ->map(fn ($word) => $word . '*')
                ->implode(' ');

            $query->whereFullText(['first_name', 'last_name', 'email'], $terms, ['mode' => 'boolean']);
        }

        return $query->paginate(self::PER_PAGE);
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
