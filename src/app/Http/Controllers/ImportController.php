<?php

namespace App\Http\Controllers;

use App\Contracts\ImportServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function store(Request $request, ImportServiceInterface $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xml'],
        ]);

        $result = $service->import($request->file('file'));

        return response()->json($result);
    }

    public function rejected(Request $request, ImportServiceInterface $service): JsonResponse
    {
        $rejected = $service->listRejected($request->only(['search']));

        return response()->json($rejected);
    }

    public function truncateContacts(ImportServiceInterface $service): JsonResponse
    {
        $service->truncateContacts();

        return response()->json(['message' => 'Contacts table truncated.']);
    }
}
