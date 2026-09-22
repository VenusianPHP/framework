<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Sketch
{
    public function __construct(public readonly string $name) {}
}
