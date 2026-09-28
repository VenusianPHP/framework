<?php

namespace Voyager\Contracts\IOPools;

enum WakeReason: string
{
    case READABLE = 'read';
    case WRITEABLE = 'write';
    case CONTROL_SIGNAL = 'signal';
    case PROCESS_EXIT = 'exit';
    //case ThreadWake;
    case FILE_CHANGE = 'file-change';
}