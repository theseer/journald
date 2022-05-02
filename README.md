# JournalWriter

A simple Library to write to Systemd's Journald from PHP.

```php

function sample() {
    throw new RuntimeException('Test Exception Message');
}

try {
    sample();
} catch (Throwable $t) {
    (new JournalWriter(SocketPath::default()))->write(
        LogEntry::fromThrowable($t)
    );
}

(new JournalWriter(SocketPath::default()))->write(
    LogEntry::fromMessage('This is a test')
);

```
