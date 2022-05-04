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

use function pack;
use function socket_clear_error;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_last_error;
use function socket_send;
use function socket_strerror;
use function sprintf;
use function str_contains;
use function strlen;

final class JournalWriter {
    public function __construct(
        private SocketPath $socketPath
    ) {
    }

    /**
     * @throws JournalWriterException
     */
    public function write(JournalEntry $entry): void {
        $sock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_clear_error($sock);

        if (!socket_connect($sock, $this->socketPath->asString())) {
            $error = socket_last_error($sock);

            throw new JournalWriterException(
                sprintf(
                    'Failed to connect to journald: %s (error %d)',
                    socket_strerror($error),
                    $error,
                )
            );
        }

        $payload = '';

        foreach ($entry as $key => $value) {
            if (str_contains($value, "\n")) {
                $payload .= sprintf(
                    "%s\n%s%s\n",
                    $key,
                    pack('P', strlen($value)),
                    $value
                );

                continue;
            }

            $payload .= sprintf("%s=%s\n", $key, $value);
        }

        $len = strlen($payload);

        $res = socket_send($sock, $payload, $len, 0);

        if ($res === false || $res !== $len) {
            $error = socket_last_error($sock);

            if ($error !== 0) {
                throw new JournalWriterException(
                    sprintf(
                        'Failed to write log entry: %s (error %d - wrote %d of %d bytes)',
                        socket_strerror($error),
                        $error,
                        (int)$res,
                        $len
                    )
                );
            }

            throw new JournalWriterException(
                sprintf(
                    'Failed to write to journald socket - no error code available (%d of %d bytes written',
                    (int)$res,
                    $len
                )
            );
        }

        socket_close($sock);
    }
}
