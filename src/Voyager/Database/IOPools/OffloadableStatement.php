<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

/** Raw statements OffloadedConnection will run. Anything else stays on the connection that owns it. */
enum OffloadableStatement: string
{
    case SELECT = 'select';
    case SELECT_ONE = 'selectOne';
    case SELECT_FROM_WRITE_CONNECTION = 'selectFromWriteConnection';
    case SCALAR = 'scalar';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case STATEMENT = 'statement';
    case AFFECTING_STATEMENT = 'affectingStatement';
    case UNPREPARED = 'unprepared';
}
