<?php
declare(strict_types=1);

namespace CodeBuilder;

use CodeBuilder\Build\GoogleClosure;
use CodeBuilder\Exception\BuilderException;
use CodeBuilder\Exception\CompileException;
use Symfony\Component\Yaml\Yaml;

class Builder
{
    /**
     * @var string
     */
    private $inputDir;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var array
     */
    private $externs = [];

    /**
     * @var string
     */
    private $outputDir;

    /**
     * @var bool
     */
    private $isCheckOnly;

    /**
     * @param string $outputDir
     * @param string $inputDir
     * @param string $bundleName
     * @param bool   $isCheckOnly
     * @param string $cacheDir
     *
     * @throws BuilderException
     */
    public function run(string $inputDir, string $outputDir, string $bundleName, bool $isCheckOnly, string $cacheDir): void
    {
        $this->outputDir = $outputDir;
        $this->inputDir = $inputDir;
        $this->isCheckOnly = $isCheckOnly;
        $this->cacheDir = $cacheDir;

        $externFile = $this->inputDir . 'externs/list.yaml';
        if (is_readable($externFile)) {
            $this->externs = Yaml::parse(file_get_contents($externFile));
        }

        $fileBundles = $this->inputDir . 'bundles-config.yaml';
        if (!is_readable($fileBundles)) {
            throw new BuilderException(
                'Bundle file ' . $fileBundles . ' not found',
                BuilderException::BOUNDLES_FILE_NOT_FOUND
            );
        }

        $jsBundles = Yaml::parse(file_get_contents($fileBundles));

        if ($bundleName) {
            if (!key_exists($bundleName, $jsBundles)) {
                throw new BuilderException(
                    'Bundle ' . $bundleName . ' not found in ' . $fileBundles,
                    BuilderException::BOUNDLE_NOT_FOUND_IN_CONFIG
                );
            }

            $jsBundles = [$bundleName => $jsBundles[$bundleName]];
        }

        foreach ($jsBundles as $name => $file) {
            $this->build($name, $file . '.js');
        }
    }

    private function build(string $buildName, string $file): void
    {
        $googleClosure = new GoogleClosure('/tmp/build/' . $buildName, $this->externs);
        $list = $googleClosure->run($file, $this->inputDir);

        $runRoot = realpath(dirname($_SERVER['PHP_SELF'])) . DIRECTORY_SEPARATOR;

        echo $buildName, "\t";
        $outputFile = $this->outputDir . $buildName . '.js';

        $cmdList = [
            '-jar ' . $runRoot . 'bin/closure-compiler.jar',
            '--compilation_level ADVANCED',
            '--warning_level VERBOSE',
            '--generate_exports',
            // '--formatting PRETTY_PRINT',
            '--jscomp_error checkTypes',
            '--jscomp_error misplacedTypeAnnotation',
            '--jscomp_error missingReturn',
            '--jscomp_error newCheckTypes',
            '--jscomp_error nonStandardJsDocs',
            '--jscomp_error strictModuleDepCheck',
            '--jscomp_error undefinedVars',
            '--jscomp_error visibility',
            '--jscomp_error accessControls',
            '--jscomp_error checkDebuggerStatement',
            '--jscomp_error checkRegExp',
            '--jscomp_error deprecated',
            '--jscomp_error externsValidation',
            // '--jscomp_error=lintChecks',

            '--hide_warnings_for ' . $runRoot . 'node_modules/google-closure-library/closure/goog/base.js',

            '--new_type_inf',
            '--dependency_mode STRICT',
            '--module_resolution BROWSER',
            '--output_wrapper "(function() {%output%}).call(window);"',
            '--language_in ECMASCRIPT6',
            '--language_out ECMASCRIPT5_STRICT',
            '--js_output_file ' . $outputFile . ' ' .
            '--js ' . $runRoot . 'node_modules/google-closure-library/closure/goog/base.js',
        ];

        if ($this->isCheckOnly) {
            $cmdList[] = '--checks_only';
        }

        $cmd = 'cd ' . $googleClosure->getSaveModuleDir() . ' && java ' . implode(' ', $cmdList);

        $cmd .= ' --entry_point "' . end($list) . '"';

        $cmd .= ' --js';
        foreach ($list as $file) {
            $cmd .= ' "' . $file . '"';
        }

        $cacheNow = [];
        $cacheFile = $this->cacheDir . $buildName . '.txt';
        if ($this->cacheDir) {
            $cacheOld = is_readable($cacheFile) ? Yaml::parse(file_get_contents($cacheFile)) : [];

            foreach ($list as $file) {
                $md5 = md5(md5_file($googleClosure->getSaveModuleDir() . $file) . $file);
                $cacheNow[] = $md5;
            }

            $cacheDiff = array_diff($cacheNow, $cacheOld);
            if (!$cacheDiff) {
                echo 'CACHE', PHP_EOL;
                return;
            }
        }

        foreach ($googleClosure->getExterns() as $externFile) {
            $cmd .= ' --externs "' . $externFile . '"';
        }

        $cmd .= ' 2>&1';

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            echo 'ERROR', PHP_EOL;
            throw new CompileException($cmd, $output, $exitCode);
        }

        if (!is_file($outputFile)) {
            echo 'ERROR', PHP_EOL;
            throw new BuilderException(
                'File "' . $outputFile . '" not found',
                BuilderException::OUTPUT_FILE_NOT_FOUND
            );
        }

        $outFileSize = filesize($outputFile);
        if (!$outFileSize) {
            echo 'ERROR', PHP_EOL;
            throw new BuilderException(
                'File size of "' . $outputFile . '" is zero',
                BuilderException::OUTPUT_FILE_SIZE_IS_ZERO
            );
        }

        if ($this->cacheDir) {
            $cacheRaw = Yaml::dump($cacheNow);

            $fw = @fopen($cacheFile, 'w');
            if (!$fw) {
                echo 'ERROR', PHP_EOL;
                throw new BuilderException(
                    'Can\'t write in the cache file ' . $cacheFile,
                    BuilderException::TROUBLE_WITH_CACHE_FILE
                );
            }

            fwrite($fw, $cacheRaw);
            fclose($fw);
        }

        echo 'OK', PHP_EOL;
        echo 'File ' . $outputFile . ' is ' . $outFileSize, PHP_EOL;
    }
}
