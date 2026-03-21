<?php

namespace App\Services;

use App\Contracts\ContactServiceInterface;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ContactService implements ContactServiceInterface
{
    private const PER_PAGE = 25;

    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');

        if ($search === '') {
            return Contact::orderBy('last_name')->orderBy('first_name')->paginate(self::PER_PAGE);
        }

        return $this->searchQuery($search)->paginate(self::PER_PAGE);
    }

    private function searchQuery(string $search): Builder
    {
        // Strip non-alphanumeric characters (except underscore) and build a boolean FULLTEXT
        // term string where each word is required (+) and prefix-matched (*).
        // e.g. "john gmail" → "+john* +gmail*"
        // Email matching is handled separately via LIKE, so @ and . are intentionally stripped
        // here — FULLTEXT tokenises on them anyway and "com" etc. would match too broadly.
        $terms = collect(preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $search)))
            ->filter()
            ->map(fn ($word) => '+'.$word.'*')
            ->implode(' ');

        return Contact::query()
            ->select('*')
            // Score exact and partial matches so they sort above fuzzy FULLTEXT-only results.
            ->selectRaw('
                CASE
                    WHEN email = ? THEN 100
                    WHEN CONCAT(first_name, \' \', last_name) = ? THEN 90
                    WHEN CONCAT(last_name, \' \', first_name) = ? THEN 90
                    WHEN email LIKE ? THEN 50
                    WHEN first_name LIKE ? OR last_name LIKE ? THEN 30
                    ELSE 10
                END as score
            ', [
                $search,
                $search,
                $search,
                "%$search%",
                "%$search%",
                "%$search%",
            ])
            ->where(function ($q) use ($search, $terms) {
                $q->where('email', $search)
                    ->orWhere('email', 'LIKE', "%$search%")
                    ->orWhereFullText(['first_name', 'last_name', 'email'], $terms, ['mode' => 'boolean']);
            })
            ->orderByDesc('score')
            ->orderBy('last_name');
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
