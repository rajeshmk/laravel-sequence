<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Enums;

enum OverflowStrategy: string
{
    case CYCLE = 'cycle'; // wrap to min
    case FAIL = 'fail';  // throw exception
}
