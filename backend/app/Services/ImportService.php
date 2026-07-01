<?php

namespace App\Services;

use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportService
{
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx'];

    public function preview(UploadedFile $file): array
    {
        $extension = $this->resolveExtension($file);
        $storedPath = $this->storeFile($file, $extension);
        
        $import = Import::create([
            'type' => $extension,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'preview',
        ]);

        $conflicts = $this->checkConflicts($import);

        return [
            'import' => $import,
            'conflicts' => $conflicts,
        ];
    }

    public function confirmImport(int $id): Import
    {
        $import = Import::findOrFail($id);

        $this->processWithPython($import);

        return $import->fresh();
    }

    private function checkConflicts(Import $import): array
    {
        $response = Http::baseUrl(config('services.python_service.url'))
            ->timeout(120)
            ->get("/imports/{$import->id}/conflicts");

        if ($response->failed()) {
            return ['error' => 'Failed to check conflicts — python-service returned ' . $response->status()];
        }

        return $response->json() ?? ['error' => 'Failed to check conflicts — python-service returned no output'];
    }

    private function processWithPython(Import $import): void
    {
        $response = Http::baseUrl(config('services.python_service.url'))
            ->timeout(120)
            ->post("/imports/{$import->id}/process");

        $result = $response->failed() ? null : $response->json();

        if (!$result || ($result['error'] ?? null)) {
            $import->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? "python-service returned {$response->status()}",
            ]);
        }
    }

    public function findOrFail(int $id): Import
    {
        return Import::findOrFail($id);
    }

    public function listPaginated(int $perPage = 20)
    {
        return Import::latest()->paginate($perPage);
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Unsupported file type: {$extension}");
        }

        return $extension;
    }

    private function storeFile(UploadedFile $file, string $extension): string
    {
        $storedName = Str::uuid() . '.' . $extension;
        $file->storeAs('imports', $storedName);

        return 'imports/' . $storedName;
    }
}
