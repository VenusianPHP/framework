<?php

namespace Voyager\Core\Console;

use ReflectionClass;
use Voyager\Console\GeneratorCommand;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Sketches\Sketch as FrameworkSketch;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;

#[AsCommand(name: 'make:sketch')]
class SketchMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:sketch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new sketch class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Sketch';

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Voyager\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result === false) {
            return $result;
        }

        $name = $this->qualifyClass($this->getNameInput());

        if ($this->scannedNamespace() === null) {
            $this->components->warn(sprintf(
                'No sketch path under [%s] is scanned, so rocket will not find [%s]. Pass its directory or the class to withSketches() in bootstrap/app.php.',
                $this->kernel()->discoveryPath(),
                $name,
            ));
        }

        return $result;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/sketch.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->venusian->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Build the class, extending the nearest abstract Sketch in its namespace or the framework's.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Voyager\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass(string $name): string
    {
        $base = $this->baseSketchFor($name);
        $class = class_basename($name);
        $alias = class_basename($base) === $class ? 'Base'.$class : class_basename($base);

        $import = match (true) {
            $alias !== class_basename($base) => "use {$base} as {$alias};",
            $this->getNamespace($base) === $this->getNamespace($name) => null,
            default => "use {$base};",
        };

        $stub = parent::buildClass($name);
        $stub = $import === null
            ? str_replace("{{ baseImport }}\n", '', $stub)
            : str_replace('{{ baseImport }}', $import, $stub);

        return str_replace('{{ base }}', $alias, $stub);
    }

    /**
     * Get the default namespace for the class: the scanned sketch path under the discovery
     * root, else Console\Sketches, the directory withSketches() scans by default.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $this->scannedNamespace() ?? $rootNamespace.'\Console\Sketches';
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return $this->kernel()->discoveryNamespace();
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return rtrim($this->kernel()->discoveryPath(), '/').'/'.str_replace('\\', '/', $name).'.php';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the sketch already exists'],
        ];
    }

    private function kernel(): SketchKernel
    {
        return $this->venusian->make(SketchKernel::class);
    }

    /**
     * The namespace of the scanned sketch path under the discovery root, preferring one the
     * app named in withSketches() over app/Console/Sketches, which setup() always scans.
     */
    private function scannedNamespace(): ?string
    {
        $root = self::absolute($this->kernel()->discoveryPath());
        $default = self::absolute($this->venusian->path('Console/Sketches'));
        $found = [];

        foreach ($this->kernel()->sketchPaths() as $path) {
            $path = self::absolute($path);

            if ($path === $root || str_starts_with($path, $root.'/')) {
                $found[$path] = trim(rtrim($this->rootNamespace(), '\\').'\\'.str_replace('/', '\\', substr($path, strlen($root) + 1)), '\\');
            }
        }

        $named = array_diff_key($found, [$default => true]);

        return reset($named) ?: ($found[$default] ?? null);
    }

    /**
     * The nearest abstract Sketch subclass named Sketch, walking up from the class's namespace
     * to the root namespace, else the framework's.
     *
     * @return class-string<FrameworkSketch>
     */
    private function baseSketchFor(string $name): string
    {
        $root = trim($this->rootNamespace(), '\\');
        $namespace = $this->getNamespace($name);

        while (str_starts_with($namespace, $root)) {
            $candidate = $namespace.'\\Sketch';

            if ($candidate !== $name
                && class_exists($candidate)
                && is_subclass_of($candidate, FrameworkSketch::class)
                && (new ReflectionClass($candidate))->isAbstract()) {
                return $candidate;
            }

            if ($namespace === $root) {
                break;
            }

            $namespace = $this->getNamespace($namespace);
        }

        return FrameworkSketch::class;
    }

    /**
     * The absolute form of a path that may not exist yet: its deepest existing ancestor
     * through realpath(), the rest resolved by its segments.
     */
    private static function absolute(string $path): string
    {
        $missing = [];

        while (($real = realpath($path)) === false) {
            $parent = dirname($path);

            if ($parent === $path) {
                return rtrim($path, '/');
            }

            array_unshift($missing, basename($path));
            $path = $parent;
        }

        foreach ($missing as $segment) {
            $real = match ($segment) {
                '', '.' => $real,
                '..' => dirname($real),
                default => $real.'/'.$segment,
            };
        }

        return rtrim($real, '/');
    }
}
