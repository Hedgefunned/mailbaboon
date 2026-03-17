<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

class EmailAddress implements ValidationRule
{
    /**
     * Custom validation rule for validating email addresses.
     * Currently it only checks if the email address is in a valid using Laravel's built-in RFC validation rule.
     * This can be extended in the future to include additional checks, such as verifying the domain or checking for disposable email addresses.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => 'email:rfc'],
        );

        if ($validator->fails()) {
            $fail($validator->errors()->first($attribute));
        }
    }
}
