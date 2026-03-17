<?php

namespace App\Http\Requests;

use App\Rules\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contactId = $this->route('contact')?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', new EmailAddress, "unique:contacts,email,{$contactId}"],
        ];
    }
}
