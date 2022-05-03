# Journald

A simple Library to write to Systemd's Journald from PHP.

```php
use theseer\journald\JournalWriter;
use theseer\journald\JournalEntry;
use theseer\journald\SocketPath;

function sample() {
    throw new RuntimeException('Test Exception Message');
}

try {
    sample();
} catch (Throwable $t) {
    (new JournalWriter(SocketPath::default()))->write(
        JournalEntry::fromThrowable($t)
    );
}

(new JournalWriter(SocketPath::default()))->write(
    JournalEntry::fromMessage('This is a test')
);

```
