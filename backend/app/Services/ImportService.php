<?php

namespace App\Services;

use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

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
        $csvPath = storage_path('app/' . $import->stored_path);
        $scriptPath = base_path('scripts/check_conflicts.py');

        $result = $this->runPython($scriptPath, [
            $csvPath,
            $import->type,
        ]);

        return $result ?? ['error' => 'Failed to check conflicts — Python script returned no output'];
    }

    private function processWithPython(Import $import): void
    {
        $csvPath = storage_path('app/' . $import->stored_path);
        $scriptPath = base_path('scripts/run_import.py');

        $result = $this->runPython($scriptPath, [
            $csvPath,
            (string) $import->id,
        ]);

        if ($result && isset($result['error']) && $result['error']) {
            $import->update([
                'status' => 'failed',
                'error_message' => $result['error'],
            ]);
        }
    }

    private function runPython(string $scriptPath, array $extraArgs): ?array
    {
        $python = config('services.python.path', 'python');

        $args = array_merge([$python, $scriptPath], $extraArgs, [
            config('database.connections.pgsql.host'),
            (string) config('database.connections.pgsql.port'),
            config('database.connections.pgsql.database'),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.password'),
        ]);

        $process = new Process($args);
        $process->setTimeout(120);
        $process->run();

        return json_decode($process->getOutput(), true);
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
