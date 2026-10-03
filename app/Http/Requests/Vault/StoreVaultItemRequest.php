<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class StoreVaultItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ciphertext' => ['required', 'string', 'max:65536'],
            'iv' => ['required', 'string', 'max:32'],
            'version' => ['sometimes', 'integer', 'min:1', 'max:255'],
        ];
    }
}
