<?php declare(strict_types=1);
namespace theseer\journald;

final class JournalWriter {

    public function __construct(
       private SocketPath $socketPath
    ) {}

    public function write(JournalEntry $entry): void {
        $sock = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_connect($sock, $this->socketPath->asString());

        $payload = '';
        foreach($entry as $key => $value) {
            if (\str_contains($value, "\n")) {
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

        socket_send($sock, $payload, $len, 0);
        socket_close($sock);
    }
}
