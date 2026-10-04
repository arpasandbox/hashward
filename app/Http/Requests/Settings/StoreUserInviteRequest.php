<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHost() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $email = strtolower((string) $this->input('email'));

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::notIn([strtolower((string) $this->user()?->email)]),
                Rule::unique(User::class, 'email'),
                function (string $attribute, mixed $value, \Closure $fail) use ($email): void {
                    $pending = UserInvite::query()
                        ->pending()
                        ->where('email', $email)
                        ->exists();

                    if ($pending) {
                        $fail('An active invite already exists for this email.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower((string) $this->input('email')),
            ]);
        }
    }
}
