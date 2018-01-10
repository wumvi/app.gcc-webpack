<?php
declare(strict_types=1);

namespace CodeBuilder\Exception;

class CompileException extends \Exception
{
    public function __construct(string $cmd, array $msg, int $code = 0)
    {
        $message = implode(PHP_EOL, $msg);
        $message .= PHP_EOL . PHP_EOL . 'Use cmd for debug';
        $message .= PHP_EOL . PHP_EOL . $cmd;
        parent::__construct($message, $code, null);
    }
}
