<?php declare(strict_types=1);
namespace theseer\journald;

use ArrayIterator;
use IteratorAggregate;
use function bin2hex;
use function debug_backtrace;
use function hexdec;
use function random_bytes;
use function sprintf;
use function substr;
use Throwable;

final class JournalEntry implements IteratorAggregate {

    private array $data = [];

    /**
     * @throws JournalEntryException
     */
    private function __construct(array $values) {
        $this->createId();

        foreach($values as $key => $value) {
            $this->addValue($key, $value);
        }
    }

    public static function fromMessage(string $message): self {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 1)[0];
        return new self(
            [
                'MESSAGE' => $message,
                'CODE_FILE' => $trace['file'] ?? 'unknown',
                'CODE_LINE' => (string)$trace['line'],
                'CODE_FUNC' => sprintf(
                    '%s%s%s',
                    $trace['class'],
                    $trace['type'],
                    $trace['function']
                )
            ]
        );
    }

    public static function fromThrowable(Throwable $throwable): self {
        return new self(
            [
                'MESSAGE' => $throwable->getMessage(),
                'CODE_FILE' => $throwable->getFile(),
                'CODE_LINE' => (string)$throwable->getLine(),
                'CODE_FUNC' => $throwable->getTrace()[0]['function'],
                'ERRNO' => (string)$throwable->getCode(),
                'CLASS' => \get_class($throwable),
                'TRACE' => $throwable->getTraceAsString()
            ]
        );
    }

    public function addValue(string $key, string $value) {
        $caps = \strtoupper($key);
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

    private function createId(): void {
        try {
            $bytes = random_bytes(16);
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            throw new JournalEntryException('Failed to create UUID', previous: $e);
        }
        // @codeCoverageIgnoreEnd

        $bytes = bin2hex($bytes);

        $this->data['MESSAGE_ID'] =sprintf(
            '%08s-%04s-4%03s-%04x-%012s',
            substr($bytes, 0, 8),
            substr($bytes, 8, 4),
            substr($bytes, 13, 3),
            hexdec(substr($bytes, 16, 4)) & 0x3fff | 0x8000,
            substr($bytes, 20, 12)
        );
    }

    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->data);
    }
}
