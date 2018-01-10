<?php
declare(strict_types = 1);

namespace CodeBuilder\Build;

/**
 * Модель для импорта, без объектов
 */
class ImportOnly
{
    /**
     * Имя файла
     *
     * @var string
     */
    private $file;

    /**
     * Constructor.
     *
     * @param string $file Имя файла
     */
    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /**
     * Возвращает имя файла
     *
     * @return string Имя файла
     */
    public function getFile(): string
    {
        return $this->file;
    }
}
