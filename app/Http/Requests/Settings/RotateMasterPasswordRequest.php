<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RotateMasterPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasVaultSetup();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kdf_salt' => ['required', 'string', 'max:64'],
            'kdf_iterations' => ['required', 'integer', 'min:600000', 'max:2000000'],
            'vault_check' => ['required', 'string', 'max:4096'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.ciphertext' => ['required', 'string', 'max:65536'],
            'items.*.iv' => ['required', 'string', 'max:32'],
            'items.*.version' => ['sometimes', 'integer', 'min:1', 'max:255'],
        ];
    }
}
