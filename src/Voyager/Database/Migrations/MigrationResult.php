<?php

namespace Voyager\Database\Migrations;

enum MigrationResult: int
{
    case Success = 1;
    case Failure = 2;
    case Skipped = 3;
}
