<?php
declare(strict_types=1);

namespace CodeBuilder\Exception;

class BuilderException extends \Exception
{
    public const BOUNDLES_FILE_NOT_FOUND = 1;
    public const WRONG_FILE = 2;
    public const IMPORT_FILE_NOT_FOUND = 4;
    public const UNKNOWN_TYPE_OF_VARIABLE = 5;
    public const NOT_FOUND_ORIGIN = 6;
    public const MODULE_NOT_FOUND = 7;
    public const EXTERN_NOT_FOUND_IN_CONFIG = 8;
    public const EXTERN_FILE_NOT_FOUND_IN_FOLDER = 9;
    public const BOUNDLE_NOT_FOUND_IN_CONFIG = 10;
    public const TROUBLE_WITH_CACHE_FILE = 11;
    public const OUTPUT_FILE_SIZE_IS_ZERO = 12;
    public const OUTPUT_FILE_NOT_FOUND = 13;
}
