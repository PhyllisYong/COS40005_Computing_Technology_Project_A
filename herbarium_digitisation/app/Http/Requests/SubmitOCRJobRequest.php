<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOCRJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // later can restrict to logged-in users
    }

    public function rules(): array
    {
        return [
            'run_name' => ['required', 'string', 'max:128'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,tiff,tif', 'max:51200'],
        ];
    }
}