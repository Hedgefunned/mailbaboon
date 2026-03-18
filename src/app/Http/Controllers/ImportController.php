<?php

namespace App\Http\Controllers;

use App\Contracts\ImportServiceInterface;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function stream(Request $request, ImportService $service): StreamedResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xml'],
        ]);

        $file = $request->file('file');

        return response()->stream(function () use ($file, $service) {
            $emit = function (array $payload): void {
                echo json_encode($payload)."\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $result = $service->import(
                $file,
                function (string $step, int $percent, string $label) use ($emit): void {
                    $emit(['type' => 'progress', 'step' => $step, 'percent' => $percent, 'label' => $label]);
                },
            );

            $emit(['type' => 'result', 'data' => $result]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
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
