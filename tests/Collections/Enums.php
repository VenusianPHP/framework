<?php

namespace Tests\Collections;

/**
 * Ported from Illuminate\Tests\Support\StaffEnum, used by SupportCollectionTest
 * for keyed-by-enum and firstWhere-by-enum assertions.
 */
enum StaffEnum
{
    case Taylor;
    case Joe;
    case James;
}
