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

use function array_slice;
use function bin2hex;
use function debug_backtrace;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function strtoupper;
use ArrayIterator;
use IteratorAggregate;
use Throwable;

/** @template-implements IteratorAggregate<string, string> */
final class JournalEntry implements IteratorAggregate {
    /** @var array<string,string> */
    private array $data = [];

    /**
     * @throws JournalEntryException
     */
    public static function fromMessage(string $message, int $traceOffset = 0): self {
        $trace = array_slice(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: $traceOffset + 2),
            $traceOffset
        );
        if (count($trace) !== 2) {
            throw new JournalEntryException(
                sprintf('Failed to capture trace context with offset %d', $traceOffset)
            );
        }

        return new self(
            [
                'MESSAGE'   => $message,
                'CODE_FILE' => $trace[0]['file'] ?? 'unknown',
                'CODE_LINE' => (string)($trace[0]['line'] ?? 0),
                'CODE_FUNC' => sprintf(
                    '%s%s%s',
                    $trace[1]['class'] ?? '',
                    $trace[1]['type'] ?? '',
                    $trace[1]['function'] ?? '{unknown}'
                )
            ]
        );
    }

    /**
     * @throws JournalEntryException
     */
    public static function fromThrowable(Throwable $throwable): self {
        $trace    = $throwable->getTrace()[0] ?? [];
        $function = sprintf(
            '%s%s%s',
            $trace['class'] ?? '',
            $trace['type'] ?? '',
            $trace['function'] ?? '{unknown}'
        );

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

        $bytes[6] = ($bytes[6] & "\x0F") | "\x40";
        $bytes[8] = ($bytes[8] & "\x3F") | "\x80";
        $bytes    = bin2hex($bytes);

        $this->data['MESSAGE_ID'] = preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', '\\1-\\2-\\3-\\4-\\5', $bytes);
    }
}
