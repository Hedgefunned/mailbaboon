<?php

namespace App\Http\Controllers;

use App\Services\ChunkedImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class ChunkedImportController extends Controller
{
    public function store(Request $request, ChunkedImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xml'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ]);

        $result = $service->import($request->file('file'), $request->boolean('overwrite_existing'));

        return response()->json($result);
    }

    public function status(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Batch not found.'], 404);
        }

        $payload = [
            'id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished_at' => $batch->finishedAt,
            'cancelled_at' => $batch->cancelledAt,
        ];

        $finalResult = Cache::get("import_{$batchId}_result");
        if ($finalResult) {
            $payload['result'] = $finalResult;
        }

        return response()->json($payload);
    }
}
