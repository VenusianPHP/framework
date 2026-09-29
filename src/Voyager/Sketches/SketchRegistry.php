<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use InvalidArgumentException;
use ReflectionClass;
use Voyager\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Voyager\Contracts\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchRegistry as RegistryContract;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\NutsAndBolts\DataObjects\Str;

class SketchRegistry implements RegistryContract
{
    /** @var array<string, class-string<Sketch>> */
    protected array $sketches = [];

    public function __construct(protected TheServiceContainer $container) {}

    public function register(string $class): void
    {
        if (! is_subclass_of($class, Sketch::class)) {
            throw new InvalidArgumentException("[{$class}] is not a sketch.");
        }

        $name = static::nameFor($class);

        if (isset($this->sketches[$name])) {
            throw new InvalidArgumentException("Sketch name [{$name}] is already registered to [{$this->sketches[$name]}].");
        }

        $this->sketches[$name] = $class;
    }

    public function has(string $name): bool { return isset($this->sketches[$name]); }

    public function all(): array { return $this->sketches; }

    public function resolve(string $name): Sketch
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Unknown sketch [{$name}].");
        }

        return $this->container->make($this->sketches[$name]);
    }

    /** @param class-string<Sketch> $class */
    public static function nameFor(string $class): string
    {
        $attributes = (new ReflectionClass($class))->getAttributes(SketchAttribute::class);

        return $attributes === []
            ? Str::kebab(class_basename($class))
            : $attributes[0]->newInstance()->name;
    }
}
