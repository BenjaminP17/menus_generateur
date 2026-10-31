<?php

namespace App\Tests\Security;

use App\Security\Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Credentials::class)]
final class CredentialsTest extends TestCase
{
    private Credentials $credentials;

    protected function setUp(): void
    {
        $this->credentials = new Credentials('test', password_hash('bon-mot-de-passe', \PASSWORD_BCRYPT));
    }

    public function testAcceptsTheRightIdentifiantAndPassword(): void
    {
        self::assertTrue($this->credentials->verify('test', 'bon-mot-de-passe'));
    }

    public function testRejectsAWrongPassword(): void
    {
        self::assertFalse($this->credentials->verify('test', 'mauvais-mot-de-passe'));
    }

    public function testRejectsAWrongIdentifiant(): void
    {
        self::assertFalse($this->credentials->verify('quelquun-dautre', 'bon-mot-de-passe'));
    }

    public function testRejectsEverythingWhenNoAccountIsConfigured(): void
    {
        $credentials = new Credentials('', '');

        self::assertFalse($credentials->verify('', ''));
    }
}
