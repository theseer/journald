<?php declare(strict_types=1);
namespace theseer\journald;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketPath::class)]
class SocketPathTest extends TestCase {

    public function testCanBeCreatedWithDefaultPath(): void {
        $this->assertSame(
            '/run/systemd/journal/socket',
            (SocketPath::default())->asString()
        );
    }

    public function testCanBeCreatedWithValidCustomPath(): void {
        $this->assertSame(
            '/run/systemd/journal/socket',
            (SocketPath::custom('/run/systemd/journal/socket'))->asString()
        );
    }

    public function testNotExistingCustomPathThrowsException(): void {
        $this->expectException(SocketPathException::class);
        SocketPath::custom('/does/not/exist');
    }

    public function testUsingNonSocketPathThrowsException(): void {
        $this->expectException(SocketPathException::class);
        SocketPath::custom(__FILE__);
    }

}
