<?php
declare(strict_types=1);

namespace CodeBuilder\Build;

use CodeBuilder\Exception\BuilderException;

/**
 * Конвертация JSDOC в понятный формат Google closure compiler
 */
class GoogleClosure
{
    /**
     * @var string[]
     */
    private $availableExterns = [];

    /**
     * @var string[]
     */
    private $needExterns = [];

    /**
     * @var string
     */
    private $saveModuleDir;

    /**
     * Constructor
     *
     * @param string $saveDir Папка для временного хранения файлов
     * @param string[] $externs
     */
    public function __construct(string $saveDir, array $externs)
    {
        $this->saveModuleDir = $saveDir . DIRECTORY_SEPARATOR;
        $this->availableExterns = $externs;
    }

    /**
     * Возвращает папку для временного хранения файлов
     *
     * @return string
     */
    public function getSaveModuleDir(): string
    {
        return $this->saveModuleDir;
    }

    /**
     * Запускает проверку
     *
     * @param string $entryPoint Точка вхождения
     * @param string $root Базовая директория
     *
     * @return array Массив файлов
     */
    public function run(string $entryPoint, string $root): array
    {
        if (is_dir($this->saveModuleDir)) {
            $this->removeDirectory($this->saveModuleDir);
        }

        $fileBuff = [];
        $copyBuff = [];
        $this->parse($entryPoint, realpath($root) . DIRECTORY_SEPARATOR, $fileBuff, $copyBuff);

        return $copyBuff;
    }

    /**
     * @param string $file Точка входа
     * @param string $root Базовая папка
     * @param string[] $fileBuff Файлы которые уже обработаны
     * @param string[] $copyBuff Файлы которые нужны для компилации
     *
     * @throws BuilderException
     */
    public function parse(string $file, string $root, array &$fileBuff, array &$copyBuff): void
    {
        $subdir = dirname($file);
        $subdir = $subdir === '.' ? '' : $subdir;

        $jsFile = $root . $file;
        $tmp = realpath($jsFile);
        if (!$tmp || !is_readable($tmp)) {
            throw new BuilderException('Import file not found: ' . $jsFile, BuilderException::IMPORT_FILE_NOT_FOUND);
        }

        $jsFile = $tmp;
        unset($tmp);

        $jsCode = file_get_contents($jsFile);

        preg_match_all('#\/\/\s+@externs (.*)#im', $jsCode, $externsMatches, PREG_SET_ORDER);
        $externs = [];
        foreach ($externsMatches as $externLine) {
            $externs = array_map('trim', array_merge($externs, explode(' ', $externLine[1])));
        }

        foreach ($externs as $extern) {
            if (!array_key_exists($extern, $this->availableExterns)) {
                throw new BuilderException(
                    'Extern "' . $extern . '" not found in extern.yaml',
                    BuilderException::EXTERN_NOT_FOUND_IN_CONFIG
                );
            }

            $externFile = $root . 'externs/' . $this->availableExterns[$extern] . '.js';
            if (!is_readable($externFile)) {
                throw new BuilderException(
                    'Extern file "' . $externFile . '" not found',
                    BuilderException::EXTERN_FILE_NOT_FOUND_IN_FOLDER
                );
            }

            $this->needExterns[] = $externFile;
        }

        // get all import line
        preg_match_all('/import([^\'"]+[\'"][^\'"]+[\'"])/', $jsCode, $importMatches, PREG_SET_ORDER);
        if ($importMatches) {
            foreach ($importMatches as $importPart) {
                $list = $this->parseImportPart(trim(str_replace(["\r", "\n"], ' ', trim($importPart[1]))), $root, $subdir);
                $jsCode = $this->parseImportModel($list, $root, $fileBuff, $copyBuff, $jsCode);
            }
        }

        // for resolution mode "BROWSER"
        $jsCode = $this->convertToResolutionModeBrowser($jsCode);

        $this->saveNewFile($file, $jsCode, $copyBuff);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getPathForResolutionModeBrowser(string $path): string
    {
        if (substr($path, -3, 3) !== '.js') {
            $path .= '.js';
        }

        if (preg_match('/^\w/', $path)) {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * @param string $jsCode
     * @return string
     */
    private function convertToResolutionModeBrowser(string $jsCode): string
    {
        $jsCode = preg_replace_callback(
            '/import(.*?)from\s+[\'"]([^\'"]+)[\'"]/',
            function ($matches) {
                $name = $matches[1];
                $path = $this->getPathForResolutionModeBrowser($matches[2]);

                return 'import ' . $name . ' from "' . $path . '"';
            },
            $jsCode
        );
        $jsCode = preg_replace_callback(
            '/import\s+[\'"]([^\'"]+)[\'"]/',
            function ($matches) {
                $path = $this->getPathForResolutionModeBrowser($matches[1]);

                return 'import "' . $path . '"';
            },
            $jsCode
        );

        return $jsCode;
    }

    /**
     * Сохраняет новые файлы во временную папку
     *
     * @param string $file Имя файла
     * @param string $jsCode Код
     * @param string[] $copyBuff Файлы нужные для компиляции
     */
    private function saveNewFile(string $file, string $jsCode, array &$copyBuff): void
    {
        $dirName = dirname($file);
        $saveDir = $this->saveModuleDir . $dirName . DIRECTORY_SEPARATOR;
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $copyBuff[] = $file;
        file_put_contents($this->saveModuleDir . $file, $jsCode);
    }


    /**
     * Обрабатываем логику после парсинга
     *
     * @param array $list Список импоторта из файла
     * @param string $root Базовая папка
     * @param string[] $fileBuff Файлы которые уже обработаны
     * @param string[] $copyBuff Файлы которые нужны для компиляции
     * @param string $jsCode Код
     *
     * @throws BuilderException
     *
     * @return string Обработанный код для компиляции
     */
    private function parseImportModel(array $list, string $root, array &$fileBuff, array &$copyBuff, string $jsCode): string
    {
        foreach ($list as $alias => $import) {
            if ($import instanceof ImportModule) {
                $relative = substr($import->getFile(), strlen($root));
                $jsCode = $this->replaceJsDoc($jsCode, $relative, $import->getOrigin(), $import->getAlias());
            } elseif ($import instanceof ImportOnly) {
                $relative = substr($import->getFile(), strlen($root));
            } else {
                throw new BuilderException(
                    'Unknown type of variable ' . $import,
                    BuilderException::UNKNOWN_TYPE_OF_VARIABLE
                );
            }

            if (in_array($import->getFile(), $fileBuff)) {
                continue;
            }

            $fileBuff[] = $import->getFile();
            $this->parse($relative, $root, $fileBuff, $copyBuff);
        }

        return $jsCode;
    }

    /**
     * Чинит JSDOC для компилерра
     *
     * @param string $jsCode Код
     * @param string $relative Относительная папка, где находится самый файл
     * @param string $origin Базовое имя объекта
     * @param string $alias Алиас объекта
     *
     * @return string
     */
    private function replaceJsDoc(string $jsCode, string $relative, string $origin, string $alias): string
    {
        $filename = preg_replace('/\.js$/', '', $relative);
        $filename = str_replace(['/', '\\'], '$', $filename);
        $filename = preg_replace('/[^\w$]/', '_', $filename);

        $gcModule = $origin . '$$module$' . $filename;

        $regexp = '/@((?:param)|(?:type)|(?:return))\s*{([^}]+)}/';

        return preg_replace_callback($regexp, function ($matches) use ($gcModule, $alias) {
            $regexp = '/([<,{?]\s*)' . $alias . '(\s*[>,}=])/';
            return preg_replace($regexp, '$1' . $gcModule . '$2', $matches[0]);
        }, $jsCode);
    }

    /**
     * Удаляет директорию
     *
     * @param string $path Директория
     */
    private function removeDirectory(string $path): void
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($path);
    }

    /**
     * Парсит import из файла
     *
     * @param string $importPart Import строка
     * @param string $root Базовая папка
     * @param string $subdir Относительная папка
     *
     * @return ImportOnly[]|ImportModule[] Список моделей import после парсинга
     */
    private function parseImportPart(string $importPart, string $root, string $subdir): array
    {
        // import {Common, Ctrl as Rename} from 'common'
        if (preg_match('/({[^}]+})\s+from\s+["\']([^"\']+)["\']/', $importPart, $match)) {
            $objList = [];
            $list = $this->parseObjPart($match[1]);
            foreach ($list as $alias => $origin) {
                $file = $this->getFilenameByPrefix($match[2], $root, $subdir);
                $origin = $this->getOriginFromExportFile($file, $origin);
                $objList[$alias] = new ImportModule($origin, $alias, $file);
            }

            return $objList;
        }

        // import Common from 'common'
        if (preg_match('/(\w+)\s+from\s+["\']([^"\']+)["\']/', $importPart, $match)) {
            $file = $this->getFilenameByPrefix($match[2], $root, $subdir);
            $origin = $this->getOriginFromExportFile($file, $match[1]);
            return [$match[1] => new ImportModule($origin, $match[1], $file)];
        }

        // import 'common'
        if (preg_match('/^\s*["\']([^"\']+)["\']\s*$/', $importPart, $match)) {
            $file = $this->getFilenameByPrefix($match[1], $root, $subdir);
            return [new ImportOnly($file)];
        }

        return [];
    }

    /**
     * Возвращает базовое имя объекта, который экспортируется из файла.
     *
     * @param string $file Имя файла
     * @param string $origin Предположительное базовое имя
     *
     * @return string Базовое имя
     *
     * @throws BuilderException
     */
    private function getOriginFromExportFile(string $file, string $origin): string
    {
        $jsCode = file_get_contents($file);
        // Если есть точно такой же export default как origin
        if (preg_match('/export\s+default\s+?(\w+\s+)?' . $origin . '/', $jsCode)) {
            return $origin;
        }

        // Ищем export default origin
        if (preg_match('/export\s+default\s+(?:\w+\s+)?(\w+)\s*[{;e]/', $jsCode, $match)) {
            return $match[1];
        }

        // Ищем export default origin, если он есть в конце строки
        if (preg_match('/export\s+default\s+(?:\w+\s+)?(\w+)\s*$/', $jsCode, $match)) {
            return $match[1];
        }

        // Получаем все экспорты
        preg_match_all('/export\s*({[^}]+})/', $jsCode, $matches, PREG_SET_ORDER);
        if (!$matches) {
            throw new BuilderException('Not found origin "' . $origin . '"', BuilderException::NOT_FOUND_ORIGIN);
        }

        foreach ($matches as $match) {
            $list = $this->parseObjPart($match[1]);

            foreach ($list as $exportAlias => $exportOrigin) {
                if ($exportOrigin === $origin) {
                    return $origin;
                } elseif ($exportAlias === $origin) {
                    return $exportOrigin;
                }
            }
        }

        throw new BuilderException(
            'Not found origin "' . $origin . '" in "' . $file . '"',
            BuilderException::NOT_FOUND_ORIGIN
        );
    }

    /**
     * Возвращает имя файла, в зависимости от преффикса в начале
     *
     * @param string $file Имя файла
     * @param string $root Базовая папка
     * @param string $subDir Относительная папка
     *
     * @return string Абсолютное имя файла
     */
    private function getFilenameByPrefix(string $file, string $root, string $subDir): string
    {
        $pathPrefix = substr($file, 0, 2);
        switch ($pathPrefix) {
            case './':
            case '..':
                return $this->getRealFilename($root . $subDir . DIRECTORY_SEPARATOR, $file);
        }

        return $this->getRealFilename($root, $file);
    }

    /**
     * Возвращает абсолютное имя файла
     *
     * @param string $root Базовая папка
     * @param string $module Название модуля
     *
     * @throws BuilderException
     *
     * @return string Абсолютное имя файла
     */
    private function getRealFilename(string $root, string $module): string
    {
        $file = $root . $module;
        if (substr($module, strlen($module) - 3, 3) !== '.js') {
            $file .= '.js';
        }

        $file = realpath($file);
        if (!$file) {
            throw new BuilderException('Module not found "' . $module . '"', BuilderException::MODULE_NOT_FOUND);
        }

        return $file;
    }

    /**
     * Парсит import и вытаскиет название объектов, которые в скобках
     *
     * @param string $objPart Часть import строки, которая в скобках
     *
     * @return string[] Названия объектов. Базовый клас и его алиас
     */
    private function parseObjPart(string $objPart): array
    {
        $result = [];

        // Entry as Alias
        preg_match_all('/(\w+)\s+as\s+(\w+)\s*[,}]/', $objPart, $matches, PREG_SET_ORDER);
        if ($matches) {
            foreach ($matches as $match) {
                $result[$match[2]] = $match[1];
            }
        }

        // Without alias
        preg_match_all('/(?<=[{,])\s*(\w+)\s*[},]/', $objPart, $matches, PREG_SET_ORDER);
        if ($matches) {
            foreach ($matches as $match) {
                $result[$match[1]] = $match[1];
            }
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getExterns(): array
    {
        return array_unique($this->needExterns);
    }
}
