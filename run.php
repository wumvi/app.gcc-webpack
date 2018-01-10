<?php
declare(strict_types=1);

use CodeBuilder\Builder;
use GetOpt\GetOpt;
use GetOpt\Option;
use CodeBuilder\Exception\ArgumentsException;

include __DIR__ . '/vendor/autoload.php';

// https://github.com/google/closure-compiler/wiki/Warnings
$builder = new Builder();

try {
    $getOpt = new GetOpt();
    $optionHelp = new Option(null, 'help', GetOpt::NO_ARGUMENT);
    $optionHelp->setDescription('This help');
    $getOpt->addOption($optionHelp);

    $option = new Option('o', 'out', GetOpt::REQUIRED_ARGUMENT);
    $option->setDescription('Directory for output files');
    $option->setArgumentName('dir');
    $getOpt->addOption($option);

    $option = new Option('i', 'in', GetOpt::REQUIRED_ARGUMENT);
    $option->setDescription('Directory with source files');
    $option->setArgumentName('dir');
    $getOpt->addOption($option);

    $option = new Option('n', 'name', GetOpt::REQUIRED_ARGUMENT);
    $option->setDescription('Custom bundle name from bundle.yaml');
    $option->setArgumentName('name');
    $getOpt->addOption($option);

    $option = new Option('c', 'check-only', GetOpt::NO_ARGUMENT);
    $option->setDescription('Check files only');
    $getOpt->addOption($option);

    $option = new Option('a', 'cache-dir', GetOpt::REQUIRED_ARGUMENT);
    $option->setDescription('Cache dir');
    $option->setArgumentName('dir');
    $getOpt->addOption($option);

    try {
        $getOpt->process();
    } catch (\Exception $ex) {
        throw new ArgumentsException($ex->getMessage(), ArgumentsException::PARSE_ARGUMENTS, $ex);
    }

    $options = $getOpt->getOption('help');
    if ($options) {
        echo $getOpt->getHelpText();
        exit;
    }

    $inputDir = (string)$getOpt->getOption('in');
    $outDir = (string)$getOpt->getOption('out');
    $name = (string)$getOpt->getOption('name');
    $isCheckOnly = (bool)$getOpt->getOption('check-only');
    $cacheDir = (string)$getOpt->getOption('cache-dir');

    if (!$inputDir) {
        throw new ArgumentsException('Input dir is empty. Use --in {dir}', ArgumentsException::PARSE_ARGUMENTS);
    }

    if (!$outDir) {
        throw new ArgumentsException('Input dir is empty. Use --out {dir}', ArgumentsException::PARSE_ARGUMENTS);
    }

    $builder->run($inputDir, $outDir, $name, $isCheckOnly, $cacheDir);
} catch (\Throwable $ex) {
    $code = $ex->getCode() === 0 ? 512 : $ex->getCode();
    echo 'Error #' . $code . ' ' . $ex->getMessage(), PHP_EOL;
    exit($code);
}

