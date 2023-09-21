<?php declare(strict_types=1);
namespace theseer\journald;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use function socket_close;
use function socket_create;
use function unlink;

#[CoversClass(JournalWriter::class)]
#[UsesClass(JournalEntry::class)]
#[UsesClass(SocketPath::class)]
class JournalWriterTest extends TestCase {

    public function testWritesToSocket(): void {
        $socketPath = '/tmp/journald-writer-test' . \uniqid('-socket', true);
        $entry = JournalEntry::fromMessage('test');

        $listenSock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind( $listenSock, $socketPath);

        $writer = new JournalWriter(SocketPath::custom($socketPath));
        $writer->write($entry);

        socket_recv($listenSock, $buffer, 2048, MSG_WAITALL);

        socket_close($listenSock);
        unlink($socketPath);

        $this->assertEquals($entry->asString(), $buffer);
    }

    public function testThrowsExceptionWhenSocketCannotBeOpened(): void {
        $socketPath = '/tmp/journald-writer-test' . \uniqid('-socket', true);
        $listenSock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind( $listenSock, $socketPath);

        $writer = new JournalWriter(SocketPath::custom($socketPath));

        socket_close($listenSock);
        unlink($socketPath);

        $this->expectException(JournalWriterException::class);
        $writer->write(JournalEntry::fromMessage('test'));
    }


    public function testThrowsExceptionWhenSocketDidNotAcceptAllInput(): void {
        $socketPath = '/tmp/journald-writer-test' . \uniqid('-socket', true);
        $listenSock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind( $listenSock, $socketPath);

        $writer = new JournalWriter(SocketPath::custom($socketPath));

        try {
            $this->expectException(JournalWriterException::class);
            $writer->write(JournalEntry::fromMessage(\random_bytes(length: 1024*1024)));
        } finally {
            socket_close($listenSock);
            unlink($socketPath);
        }

    }

}
