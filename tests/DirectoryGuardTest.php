<?php

require_once dirname(__FILE__) . '/StatssearchTestCase.php';

class DirectoryGuardTest extends StatssearchTestCase
{
    public function guardFileProvider()
    {
        return array(
            array('index.php'),
            array('tests/index.php'),
            array('tests/phpstan/index.php'),
            array('translations/index.php'),
            array('upgrade/index.php'),
        );
    }

    /**
     * @dataProvider guardFileProvider
     */
    public function testDirectoryGuardExitsCleanly($relativePath)
    {
        $target = $this->repoRoot() . '/' . $relativePath;
        $this->assertFileExists($target);

        $result = $this->runPhpChild(array(), $target);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('exited', $result['sentinel']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('', $result['stderr']);
        $this->assertNoPhpDiagnostics($result['stdout'], $result['stderr']);
    }
}
