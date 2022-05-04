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

use function file_exists;
use function filetype;

final class SocketPath {
    private string $path;

    public static function default(): self {
        return new self('/run/systemd/journal/socket');
    }

    public static function custom(string $path): self {
        return new self($path);
    }

    private function __construct(string $path) {
        $this->ensureExists($path);
        $this->path = $path;
    }

    public function asString(): string {
        return $this->path;
    }

    private function ensureExists(string $path): void {
        if (!file_exists($path)) {
            throw new SocketPathException(
                sprintf('Invalid Path "%s" - not found', $path)
            );
        }
        if (filetype($path) !== 'socket') {
            throw new SocketPathException(
                sprintf('Invalid Path "%s" - not a socket', $path)
            );
        }
    }
}
