<?php
declare(strict_types = 1);

namespace CodeBuilder\Build;

/**
 * Модель импорта с объектами
 */
class ImportModule
{
    /**
     * Основное имя объекта
     *
     * @var string
     */
    private $origin;

    /**
     * Алиас объекта
     *
     * @var string
     */
    private $alias;

    /**
     * Имя файла
     *
     * @var string
     */
    private $file;

    /**
     * Constructor.
     *
     * @param string $origin Основное имя объекта
     * @param string $alias Алиас объекта
     * @param string $file Имя файла
     */
    public function __construct(string $origin, string $alias, string $file)
    {
        $this->origin = $origin;
        $this->alias = $alias;
        $this->file = $file;
    }

    /**
     * Возвращает основное имя объекта
     *
     * @return string Основное имя объекта
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Возвращает алиас объекта
     *
     * @return string Алиас объекта
     */
    public function getAlias(): string
    {
        return $this->alias;
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
