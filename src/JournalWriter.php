<?php declare(strict_types=1);
/*
 * This file is part of theseer\journald.
 *
 * Copyright (c) Arne Blankerts <arne@blankerts.de> and contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
namespace theseer\journald;

use function socket_clear_error;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_last_error;
use function socket_send;
use function socket_strerror;
use function sprintf;
use function strlen;
use Socket;

final class JournalWriter {
    public function __construct(
        private SocketPath $socketPath
    ) {
    }

    /**
     * @throws JournalWriterException
     */
    public function write(JournalEntry $entry): void {
        $sock = $this->setupSocketConnection();

        $this->writeToSocket($entry, $sock);

        $this->closeConnection($sock);
    }

    /**
     * @throws JournalWriterException
     */
    private function setupSocketConnection(): Socket {
        $sock = socket_create(AF_UNIX, SOCK_DGRAM, getprotobyname('ip'));
        assert($sock instanceof Socket);

        socket_clear_error($sock);

        if (!@socket_connect($sock, $this->socketPath->asString())) {
            $error = socket_last_error($sock);

            throw new JournalWriterException(
                sprintf(
                    'Failed to connect to journald: %s (error %d)',
                    socket_strerror($error),
                    $error,
                )
            );
        }

        return $sock;
    }

    /**
     * @throws JournalWriterException
     */
    private function writeToSocket(JournalEntry $entry, Socket $sock): void {
        $payload = $entry->asString();
        $length  = strlen($payload);

        $res = @socket_send($sock, $payload, $length, 0);

        if ($res !== $length) {
            $error = socket_last_error($sock);
            socket_close($sock);

            throw new JournalWriterException(
                sprintf(
                    'Failed to write log entry: %s (error %d - wrote %d of %d bytes)',
                    socket_strerror($error),
                    $error,
                    (int)$res,
                    $length
                )
            );
        }
    }

    private function closeConnection(Socket $sock): void {
        socket_shutdown($sock, 2);
        socket_close($sock);
    }
}
