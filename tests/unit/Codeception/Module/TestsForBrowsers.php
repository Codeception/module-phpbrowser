<?php

declare(strict_types=1);

use Codeception\Exception\ModuleException;

require_once __DIR__ . '/TestsForWeb.php';

/**
 * Author: davert
 * Date: 13.01.12
 *
 * Class TestsForBrowsers
 */
abstract class TestsForBrowsers extends TestsForWeb
{
    public function testAmOnSubdomain(): void
    {
        $this->module->_reconfigure(['url' => 'https://google.com']);
        $this->module->amOnSubdomain('user');
        $this->assertEquals('https://user.google.com', $this->module->_getUrl());

        $this->module->_reconfigure(['url' => 'https://www.google.com']);
        $this->module->amOnSubdomain('user');
        $this->assertEquals('https://user.google.com', $this->module->_getUrl());
    }

    public function testOpenAbsoluteUrls(): void
    {
        $this->module->amOnUrl('http://localhost:8000/');
        $this->module->see('Welcome to test app!', 'h1');
        $this->module->amOnUrl('http://127.0.0.1:8000/info');
        $this->module->see('Information', 'h1');
        $this->module->amOnPage('/form/empty');
        $this->module->seeCurrentUrlEquals('/form/empty');
        $this->assertEquals('http://127.0.0.1:8000', $this->module->_getUrl(), 'Host has changed');
    }

    public function testHeadersRedirect(): void
    {
        $this->module->amOnPage('/redirect');
        $this->module->seeInCurrentUrl('info');
    }

    /*
     * https://github.com/Codeception/Codeception/issues/1510
     */
    public function testSiteRootRelativePathsForBasePathWithSubdir(): void
    {
        $this->module->_reconfigure(['url' => 'http://localhost:8000/form']);
        $this->module->amOnPage('/relative_siteroot');
        $this->module->seeInCurrentUrl('/form/relative_siteroot');
        $this->module->submitForm('form', [
            'test' => 'value'
        ]);
        $this->module->dontSeeInCurrentUrl('form/form/');
        $this->module->amOnPage('relative_siteroot');
        $this->module->click('Click me');
        $this->module->dontSeeInCurrentUrl('form/form/');
    }

    public function testOpenPageException(): void
    {
        $this->expectException(ModuleException::class);
        $this->module->see('Hello');
    }
}
