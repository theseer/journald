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

use function bin2hex;
use function debug_backtrace;
use function hexdec;
use function random_bytes;
use function sprintf;
use function strtoupper;
use function substr;
use ArrayIterator;
use IteratorAggregate;
use Throwable;

final class JournalEntry implements IteratorAggregate {

    /** @var array<string,string> */
    private array $data = [];

    /**
     * @throws JournalEntryException
     */
    public static function fromMessage(string $message): self {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 1)[0];

        return new self(
            [
                'MESSAGE'   => $message,
                'CODE_FILE' => $trace['file'] ?? 'unknown',
                'CODE_LINE' => (string)($trace['line'] ?? 0),
                'CODE_FUNC' => sprintf(
                    '%s%s%s',
                    $trace['class'] ?? '',
                    $trace['type'] ?? '',
                    $trace['function']
                )
            ]
        );
    }

    /**
     * @throws JournalEntryException
     */
    public static function fromThrowable(Throwable $throwable): self {
        $trace    = $throwable->getTrace()[0] ?? [];
        $function = $trace['function'] ?? '';

        return new self(
            [
                'MESSAGE'   => $throwable->getMessage(),
                'CODE_FILE' => $throwable->getFile(),
                'CODE_LINE' => (string)$throwable->getLine(),
                'CODE_FUNC' => $function,
                'ERRNO'     => (string)$throwable->getCode(),
                'CLASS'     => $throwable::class,
                'TRACE'     => $throwable->getTraceAsString()
            ]
        );
    }

    /**
     * @param array<string,string> $values
     *
     * @throws JournalEntryException
     */
    private function __construct(array $values) {
        $this->createMessageId();

        foreach ($values as $key => $value) {
            $this->addValue($key, $value);
        }
    }

    /**
     * @throws JournalEntryException
     */
    public function addValue(string $key, string $value): void {
        $caps = strtoupper($key);

        if (!preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $caps)) {
            throw new JournalEntryException(
                sprintf('Invalid field name "%s": Journald requires a field name to match "^[A-Z][A-Z0-9_]{,63}$".', $caps)
            );
        }

        if (isset($this->data[$caps])) {
            throw new JournalEntryException(
                sprintf('Cannot overwrite already set key "%s"', $caps)
            );
        }

        $this->data[$caps] = $value;
    }

    public function asString(): string {
        $payload = '';

        foreach ($this->data as $key => $value) {
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

        return $payload;
    }

    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->data);
    }

    /**
     * @throws JournalEntryException
     */
    private function createMessageId(): void {
        try {
            $bytes = random_bytes(16);
            // @codeCoverageIgnoreStart
        } catch (Throwable $e) {
            throw new JournalEntryException('Failed to create uuid for MESSAGE_ID', previous: $e);
        }
        // @codeCoverageIgnoreEnd

        $bytes = bin2hex($bytes);

        $this->data['MESSAGE_ID'] = sprintf(
            '%08s-%04s-4%03s-%04x-%012s',
            substr($bytes, 0, 8),
            substr($bytes, 8, 4),
            substr($bytes, 13, 3),
            hexdec(substr($bytes, 16, 4)) & 0x3fff | 0x8000,
            substr($bytes, 20, 12)
        );
    }
}
