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

    /** Everything but a select changes the database, and runs alone on its connection. */
    public function writes(): bool
    {
        return ! in_array($this, [self::SELECT, self::SELECT_ONE, self::SELECT_FROM_WRITE_CONNECTION, self::SCALAR], true);
    }
}
