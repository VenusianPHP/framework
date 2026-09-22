<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches;

interface SketchRegistry
{
    /** @param class-string<Sketch> $class */
    public function register(string $class): void;
    public function has(string $name): bool;
    /** @return array<string, class-string<Sketch>> name => class */
    public function all(): array;
    public function resolve(string $name): Sketch;
}
