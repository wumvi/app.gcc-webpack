<?php
declare(strict_types = 1);

use \PHPUnit\Framework\TestCase;
use \App\Build\Convert;

class ConvertTest extends TestCase
{
    /**
     * @covers GoogleClosure::run
     */
    public function testImportOnly()
    {
        $dir = 't1';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');
        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testExportDefault1()
    {
        $dir = 't2';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testExportDefault2()
    {
        $dir = 't3';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testExportDefault3()
    {
        $dir = 't4';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }


    public function testExport1()
    {
        $dir = 't5';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testExport2()
    {
        $dir = 't6';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testImportAlias()
    {
        $dir = 't7';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['common.js', 'point.js']
        );
    }

    public function testTypeDefault()
    {
        $dir = 't8';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import Entry from \'./entry\'; /** @type {Entry$$module$entry} */'
        );
    }

    public function testTypeAlias()
    {
        $dir = 't9';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import Entry from \'./entry\'; /** @type {Object$$module$entry} */'
        );
    }

    public function testTypeDirWithTrash()
    {
        $dir = 't10';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import Entry from \'./dir/entry.ext\'; /** @type {Object$$module$dir$entry_ext} */'
        );
    }

    public function testTypeAlias2()
    {
        $dir = 't11';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import Alias from \'./entry\'; /** @type {Entry$$module$entry} */'
        );
    }

    public function testTypeAlias3()
    {
        $dir = 't12';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import {Entry as Alias} from \'./entry\'; /** @type {Entry$$module$entry} */'
        );
    }

    public function testTypeAlias4()
    {
        $dir = 't13';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import {Data as Alias} from \'./entry\'; /** @type {Entry$$module$entry} */'
        );
    }

    public function testMultiDir()
    {
        $dir = 't14';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $list = $convert->run('point.js', './' . $dir . '/');

        $this->assertEquals(
            $list,
            ['m3.js', 'dir1/dir2/m2.js', 'dir1/m1.js', 'point.js',]
        );
    }

    public function testMultiExport()
    {
        $dir = 't15';
        $convert = new Convert(sys_get_temp_dir() . '/tmp/' . $dir . '/');
        $convert->run('point.js', './' . $dir . '/');

        $tmpDir = $convert->getTmpDir();
        $jsData = trim(file_get_contents($tmpDir . 'point.js'));

        $this->assertEquals(
            $jsData,
            'import {Card, Place} from \'./common\'; /** @type {Place$$module$common} */'
        );
    }
}
