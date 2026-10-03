<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreVaultItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'max:500'],
            'items.*.ciphertext' => ['required', 'string', 'max:65536'],
            'items.*.iv' => ['required', 'string', 'max:32'],
            'items.*.version' => ['sometimes', 'integer', 'min:1', 'max:255'],
        ];
    }
}
