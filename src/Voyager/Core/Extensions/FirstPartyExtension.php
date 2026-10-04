<?php

namespace Voyager\Core\Extensions;

/**
 * The PHP extensions `install:ext` installs through PIE. Each value is the
 * token `pie install` takes: the Packagist package and its version line.
 */
enum FirstPartyExtension: string
{
    case EPOLL = 'php-io-extensions/epoll:^0.10';
    case KQUEUE = 'php-io-extensions/kqueue:^0.10';
    case PCURL = 'php-io-extensions/pcurl:^0.10';

    /** The case PHP loads under this name, or null. */
    public static function named(string $extension): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->extension() === strtolower($extension)) {
                return $case;
            }
        }

        return null;
    }

    /** The name PHP loads it under. */
    public function extension(): string
    {
        return strtolower($this->name);
    }

    public function description(): string
    {
        return match ($this) {
            self::EPOLL => 'event loop waiting on Linux',
            self::KQUEUE => 'event loop waiting on macOS',
            self::PCURL => 'HTTP requests that run on the event loop',
        };
    }

    /**
     * Why this operating system cannot have the extension, or null when it can.
     *
     * @param  string  $os_family  A PHP_OS_FAMILY value.
     */
    public function unsupportedOn(string $os_family): ?string
    {
        return match ($this) {
            self::EPOLL => $os_family === 'Linux' ? null : 'Linux only',
            self::KQUEUE => $os_family === 'Darwin' ? null : 'macOS only',
            self::PCURL => $os_family === 'Windows' ? 'not on Windows' : null,
        };
    }
}
