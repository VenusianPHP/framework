<?php

namespace Tests\NutsAndBolts\Fixtures;

enum StringBackedEnum: string
{
    case ADMIN_LABEL = 'I am \'admin\'';
    case HELLO_WORLD = 'Hello world';
}
