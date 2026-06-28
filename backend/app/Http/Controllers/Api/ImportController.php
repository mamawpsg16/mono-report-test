<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Services\ImportService;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService
    ) {}

    public function preview(StoreImportRequest $request)
    {
        $result = $this->importService->preview($request->file('file'));
        $import = $result['import'];
        $conflicts = $result['conflicts'];

        if (isset($conflicts['error'])) {
            return response()->json([
                'message' => $conflicts['error'],
            ], 422);
        }

        return response()->json([
            'id' => $import->id,
            'original_filename' => $import->original_filename,
            'new_rows' => $conflicts['new_rows'],
            'update_rows' => $conflicts['update_rows'],
            'updates' => $conflicts['updates'] ?? [],
        ]);
    }

    public function confirm(int $id)
    {
        $import = $this->importService->confirmImport($id);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'error_message' => $import->error_message,
        ]);
    }

    public function store(StoreImportRequest $request)
    {
        $result = $this->importService->preview($request->file('file'));
        $import = $result['import'];

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'original_filename' => $import->original_filename,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'error_message' => $import->error_message,
        ], 201);
    }

    public function show(int $id)
    {
        $import = $this->importService->findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'type' => $import->type,
            'original_filename' => $import->original_filename,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'error_message' => $import->error_message,
            'created_at' => $import->created_at,
            'updated_at' => $import->updated_at,
        ]);
    }

    public function index()
    {
        return response()->json($this->importService->listPaginated());
    }
}
