<?php declare(strict_types=1);
namespace theseer\journald;

final class SocketPath {

    private string $path;

    private function __construct(string $path) {
        $this->ensureExists($path);
        $this->path = $path;
    }

    public static function default(): self {
        return new self('/run/systemd/journal/socket');
    }

    public static function custom(string $path): self {
        return new self($path);
    }

    public function asString(): string {
        return $this->path;
    }

    private function ensureExists(string $path): void {
        if (!\file_exists($path)) {
            throw new SocketPathException(
                sprintf('"%s" is not a valid journald socket (not found)', $path)
            );
        }
    }

}
