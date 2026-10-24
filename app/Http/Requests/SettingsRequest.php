<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\EInvoicing\Rules\Bic;
use Modules\EInvoicing\Rules\Iban;
use Modules\EInvoicing\Support\Authorizes;

final class SettingsRequest extends FormRequest
{
    public function authorize(Authorizes $authorizes): bool
    {
        return $authorizes->canManageSettings($this);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'iban' => ['nullable', 'string', 'max:42', new Iban],
            'bic' => ['nullable', 'string', 'max:15', new Bic],
            'payment_terms' => ['nullable', 'string', 'max:500'],
        ];
    }
}
