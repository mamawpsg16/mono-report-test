<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Client\ConnectionException;
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
        return Customer::with(['creator', 'updater', 'assignedRepresentative'])
            ->visibleTo(auth()->user())
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

    /**
     * Set (or clear, with null) the owning sales rep on one customer.
     * "$repId is a real sales rep" is enforced at the boundary
     * (AssignRepresentativeRequest / Rules\SalesRepresentative).
     * Returns the fresh row with the relations the list UI renders.
     */
    public function assignRepresentative(Customer $customer, ?int $repId, int $actorId): Customer
    {
        $customer->update([
            'assigned_representative_id' => $repId,
            'updated_by' => $actorId,
        ]);

        return $customer->fresh()->load(['creator', 'updater', 'assignedRepresentative']);
    }

    /**
     * Same assignment across many customers at once (the list-view bulk
     * action). One UPDATE, returns how many rows changed.
     */
    public function assignRepresentativeBulk(array $uuids, ?int $repId, int $actorId): int
    {
        return Customer::whereIn('uuid', $uuids)->update([
            'assigned_representative_id' => $repId,
            'updated_by' => $actorId,
        ]);
    }

    private function validateWithPython(string $storedPath): array
    {
        try {
            $response = Http::baseUrl(config('services.python_service.url'))
                ->timeout(120)
                ->post('/customers/validate', ['path' => $storedPath]);
        } catch (ConnectionException) {
            return [
                'summary' => ['total_rows' => 0, 'new_count' => 0, 'update_count' => 0, 'error_count' => 1],
                'rows'    => [],
                'errors'  => [['row' => 0, 'column' => '', 'message' => 'Validation service unreachable']],
            ];
        }

        if ($response->failed()) {
            // FastAPI's HTTPException puts its real message in `detail` -- a
            // 400 means the service rejected the file for a specific reason
            // (e.g. the xlsx zip-bomb guard), which the caller should see
            // rather than a generic "unavailable" that implies an outage.
            return [
                'summary' => ['total_rows' => 0, 'new_count' => 0, 'update_count' => 0, 'error_count' => 1],
                'rows'    => [],
                'errors'  => [['row' => 0, 'column' => '', 'message' => $response->json('detail') ?? 'Validation service unavailable']],
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
        try {
            $response = Http::baseUrl(config('services.python_service.url'))
                ->timeout(120)
                ->post('/customers/process', [
                    'path' => $storedPath,
                    'original_filename' => $originalFilename,
                    'user_id' => auth()->id(),
                ]);
        } catch (ConnectionException) {
            return ['status' => 'failed', 'processed_rows' => 0, 'errors' => [['row' => 0, 'column' => '', 'message' => 'Processing service unreachable']]];
        }

        if ($response->failed()) {
            return ['status' => 'failed', 'processed_rows' => 0, 'errors' => [['row' => 0, 'column' => '', 'message' => $response->json('detail') ?? 'Processing service unavailable']]];
        }

        return $response->json() ?? ['status' => 'failed', 'processed_rows' => 0, 'errors' => [['row' => 0, 'column' => '', 'message' => 'No response from processing service']]];
    }

    public function ask(string $question, array $history = []): array
    {
        try {
            $response = Http::baseUrl(config('services.python_service.url'))
                ->timeout(120)
                ->post('/customers/ask', ['question' => $question, 'history' => $history]);
        } catch (ConnectionException) {
            return ['answer' => null, 'sources' => [], 'error' => 'RAG service unreachable'];
        }

        if ($response->failed()) {
            return ['answer' => null, 'sources' => [], 'error' => $response->json('detail') ?? 'RAG service error'];
        }

        return $response->json() ?? ['answer' => null, 'sources' => [], 'error' => 'No response from RAG service'];
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
