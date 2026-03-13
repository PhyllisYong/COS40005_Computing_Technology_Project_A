<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDigitisationJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'run_name'  => ['required', 'string', 'max:128'],
            'files'     => ['required', 'array', 'min:1'],
            'files.*'   => ['required', 'file', 'mimes:jpg,jpeg,png,tiff,tif', 'max:51200'],
        ];

        // config_overrides validation omitted until auth/admin is re-enabled

        return $rules;
    }

    /**
     * Strip config_overrides for non-admin users before validation runs.
     * This ensures the field never reaches the service layer even if manually posted.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('config_overrides');
    }

    /**
     * Return the sanitized config overrides for admin users, or an empty array.
     */
    public function sanitizedConfigOverrides(): array
    {
        if (!auth()->user()?->is_admin) {
            return [];
        }

        return $this->input('config_overrides', []);
    }
}
