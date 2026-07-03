<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerService
{
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx'];

    public function preview(UploadedFile $file): array
    {
        $extension = $this->resolveExtension($file);
        $storedPath = $this->storeFile($file, $extension);

        $diff = $this->validateWithPython($storedPath);

        if ($diff['summary']['error_count'] > 0) {
            Storage::delete($storedPath);
            return $diff;
        }

        return array_merge($diff, ['stored_path' => $storedPath]);
    }

    public function confirm(string $storedPath, string $originalFilename): array
    {
        $result = $this->processWithPython($storedPath, $originalFilename);
        Storage::delete($storedPath);

        return $result;
    }

    public function listPaginated(int $perPage = 20, ?string $search = null)
    {
        return Customer::with(['creator', 'updater'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('customer_code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('address', 'ilike', "%{$search}%")
                        ->orWhere('city', 'ilike', "%{$search}%")
                        ->orWhere('country', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    private function validateWithPython(string $storedPath): array
    {
        $response = Http::baseUrl(config('services.python_service.url'))
            ->timeout(120)
            ->post('/customers/validate', ['path' => $storedPath]);

        if ($response->failed()) {
            return [
                'summary' => ['total_rows' => 0, 'new_count' => 0, 'update_count' => 0, 'error_count' => 1],
                'rows'    => [],
                'errors'  => [['row' => 0, 'column' => '', 'message' => 'Validation service unavailable']],
            ];
        }

        return $response->json() ?? [
            'summary' => ['total_rows' => 0, 'new_count' => 0, 'update_count' => 0, 'error_count' => 1],
            'rows'    => [],
            'errors'  => [['row' => 0, 'column' => '', 'message' => 'No response from validation service']],
        ];
    }

    private function processWithPython(string $storedPath, string $originalFilename): array
    {
        $response = Http::baseUrl(config('services.python_service.url'))
            ->timeout(120)
            ->post('/customers/process', [
                'path' => $storedPath,
                'original_filename' => $originalFilename,
                'user_id' => auth()->id(),
            ]);

        if ($response->failed()) {
            return ['status' => 'failed', 'processed_rows' => 0, 'errors' => [['row' => 0, 'column' => '', 'message' => 'Processing service unavailable']]];
        }

        return $response->json() ?? ['status' => 'failed', 'processed_rows' => 0, 'errors' => [['row' => 0, 'column' => '', 'message' => 'No response from processing service']]];
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
        $file->storeAs('uploads', $storedName);

        return 'uploads/' . $storedName;
    }
}
