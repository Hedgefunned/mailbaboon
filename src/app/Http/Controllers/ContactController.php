<?php

namespace App\Http\Controllers;

use App\Contracts\ContactServiceInterface;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private ContactServiceInterface $service) {}

    public function index(Request $request): JsonResponse
    {
        $contacts = $this->service->list($request->only(['search']));

        return response()->json($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->service->create($request->validated());

        return response()->json($contact, 201);
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $contact = $this->service->update($contact, $request->validated());

        return response()->json($contact);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->service->delete($contact);

        return response()->json(null, 204);
    }
}
