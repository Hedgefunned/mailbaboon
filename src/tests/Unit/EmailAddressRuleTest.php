<?php

namespace Tests\Unit;

use App\Rules\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailAddressRuleTest extends TestCase
{
    private function validate(string $email): bool
    {
        $failed = false;

        (new EmailAddress)->validate('email', $email, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    public static function validEmails(): array
    {
        return [
            'simple' => ['user@example.com'],
            'subdomain' => ['user@mail.example.com'],
            'plus addressing' => ['user+tag@example.com'],
            'dot in local part' => ['first.last@example.com'],
            'numeric local part' => ['123@example.com'],
            'hyphen in domain' => ['user@my-domain.com'],
        ];
    }

    public static function invalidEmails(): array
    {
        return [
            'missing @' => ['userexample.com'],
            'missing domain' => ['user@'],
            'missing local part' => ['@example.com'],
            'double @' => ['user@@example.com'],
            'consecutive dots in domain' => ['user@exam..ple.com'],
            'leading dot in local part' => ['.user@example.com'],
            'trailing dot in local part' => ['user.@example.com'],
            'plain string' => ['notanemail'],
        ];
    }

    #[DataProvider('validEmails')]
    public function test_accepts_valid_email(string $email): void
    {
        $this->assertTrue($this->validate($email));
    }

    #[DataProvider('invalidEmails')]
    public function test_rejects_invalid_email(string $email): void
    {
        $this->assertFalse($this->validate($email));
    }
}
