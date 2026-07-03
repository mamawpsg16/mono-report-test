<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    public function index(Request $request)
    {
        return response()->json($this->customerService->listPaginated(
            (int) $request->query('per_page', 20),
            $request->query('search'),
        ));
    }

    public function preview(StoreImportRequest $request)
    {
        $result = $this->customerService->preview($request->file('file'));

        if (!isset($result['stored_path'])) {
            return response()->json([
                'message' => 'Validation failed',
                'summary' => $result['summary'],
                'rows'    => $result['rows'],
                'errors'  => $result['errors'],
            ], 422);
        }

        return response()->json($result);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'stored_path' => 'required|string',
            'original_filename' => 'required|string',
        ]);

        return response()->json($this->customerService->confirm(
            $request->input('stored_path'),
            $request->input('original_filename'),
        ));
    }
}
