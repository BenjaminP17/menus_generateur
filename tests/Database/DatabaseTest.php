<?php

namespace App\Tests\Database;

use App\Database\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Database::class)]
final class DatabaseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/menus-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testCreatesDirectoryAndEnablesForeignKeys(): void
    {
        $database = new Database($this->directory.'/test.sqlite');

        $pdo = $database->connection();

        $this->assertDirectoryExists($this->directory);
        $this->assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }

    public function testReusesTheSameConnection(): void
    {
        $database = new Database($this->directory.'/test.sqlite');

        $this->assertSame($database->connection(), $database->connection());
    }
}
