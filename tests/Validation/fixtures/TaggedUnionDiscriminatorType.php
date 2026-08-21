<?php

namespace Tests\Validation\fixtures;

enum TaggedUnionDiscriminatorType: string
{
    case EMAIL = 'email';
    case URL = 'url';
}
