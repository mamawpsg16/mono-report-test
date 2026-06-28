<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:csv,txt,xlsx',
                'mimetypes:text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'No file was uploaded.',
            'file.max' => 'File must be under 10MB.',
            'file.mimes' => 'Only CSV and XLSX files are allowed.',
            'file.mimetypes' => 'File content does not match an allowed type.',
        ];
    }
}
