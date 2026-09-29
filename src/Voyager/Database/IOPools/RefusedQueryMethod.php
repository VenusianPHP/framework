<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

/** Terminals via() will not run. They take a callback or yield. stream() is the loop form. */
enum RefusedQueryMethod: string
{
    case CURSOR = 'cursor';
    case LAZY = 'lazy';
    case LAZY_BY_ID = 'lazyById';
    case LAZY_BY_ID_DESC = 'lazyByIdDesc';
    case CHUNK = 'chunk';
    case CHUNK_BY_ID = 'chunkById';
    case CHUNK_BY_ID_DESC = 'chunkByIdDesc';
    case CHUNK_MAP = 'chunkMap';
    case EACH = 'each';
    case EACH_BY_ID = 'eachById';
    case TAP = 'tap';
    case WHEN = 'when';
    case UNLESS = 'unless';
}
