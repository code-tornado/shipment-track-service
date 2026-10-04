<?php

namespace App\Enums;

/** Who or what made a status or date change. */
enum ChangeSource: string
{
    case User = 'user';
    case Import = 'import';
    case Provider = 'provider';
    case System = 'system';
}
