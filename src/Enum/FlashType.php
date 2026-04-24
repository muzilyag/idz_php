<?php

namespace App\Enum;

enum FlashType: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case IMPORT_ERRORS = 'import_errors';
}
