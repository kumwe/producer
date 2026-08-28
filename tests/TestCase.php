<?php

/**
 * Minimal assertion base for the dependency-free suite.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests;

abstract class TestCase
{
    private int $assertions = 0;

    final public function assertionCount(): int
    {
        return $this->assertions;
    }

    final protected function assertTrue(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    final protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new \RuntimeException(
                $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
            );
        }
    }

    final protected function assertStringContains(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($message . ' Missing: ' . $needle);
        }
    }

    final protected function assertStringExcludes(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException($message . ' Forbidden substring present: ' . $needle);
        }
    }

    final protected function assertThrows(callable $operation, string $exceptionClass, string $message): \Throwable
    {
        $this->assertions++;
        try {
            $operation();
        } catch (\Throwable $error) {
            if (!($error instanceof $exceptionClass)) {
                throw new \RuntimeException(
                    $message . ' Threw ' . get_class($error) . ' instead of ' . $exceptionClass . ': ' . $error->getMessage()
                );
            }
            return $error;
        }
        throw new \RuntimeException($message . ' Nothing was thrown; expected ' . $exceptionClass . '.');
    }
}
