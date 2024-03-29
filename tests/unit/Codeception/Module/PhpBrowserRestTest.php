<?php

declare(strict_types=1);

use Codeception\Lib\ModuleContainer;
use Codeception\Module\PhpBrowser;
use Codeception\Module\REST;
use Codeception\Test\Cest;
use Codeception\Test\Unit;
use Codeception\Stub;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\BrowserKit\Request as SymfonyRequest;

final class PhpBrowserRestTest extends Unit
{
    protected REST $module;

    protected ?PhpBrowser $phpBrowser = null;

    protected function _setUp()
    {
        $container = Stub::make(ModuleContainer::class);
        $this->phpBrowser = new PhpBrowser($container);
        $url = 'http://localhost:8010';
        $this->phpBrowser->_setConfig(['url' => $url]);
        $this->phpBrowser->_initialize();

        $this->module = Stub::make(REST::class);
        $this->module->_inject($this->phpBrowser);
        $this->module->_initialize();
        $this->module->_before(Stub::makeEmpty(Cest::class));
        
        $this->phpBrowser->_before(Stub::makeEmpty(Cest::class));
    }

    private function setStubResponse($response)
    {
        $this->phpBrowser = Stub::make(PhpBrowser::class, ['_getResponseContent' => $response]);
        $this->module->_inject($this->phpBrowser);
        $this->module->_initialize();
        $this->module->_before(Stub::makeEmpty(Cest::class));
    }

    public function testGet()
    {
        $this->module->sendGET('/rest/user/');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContains('davert');
        $this->module->seeResponseContainsJson(['name' => 'davert']);
        $this->module->seeResponseCodeIs(200);
        $this->module->dontSeeResponseCodeIs(404);
    }

    public function testSendAbsoluteUrlGet()
    {
        $this->module->sendGET('http://127.0.0.1:8010/rest/user/');
        $this->module->seeResponseCodeIs(200);
    }

    public function testPost()
    {
        $this->module->sendPOST('/rest/user/', ['name' => 'john']);
        $this->module->seeResponseContains('john');
        $this->module->seeResponseContainsJson(['name' => 'john']);
    }

    public function testValidJson()
    {
        $this->setStubResponse('{"xxx": "yyy"}');
        $this->module->seeResponseIsJson();
        $this->setStubResponse('{"xxx": "yyy", "zzz": ["a","b"]}');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseEquals('{"xxx": "yyy", "zzz": ["a","b"]}');
    }

    public function testInvalidJson()
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('{xxx = yyy}');
        $this->module->seeResponseIsJson();
    }

    public function testValidXml()
    {
        $this->setStubResponse('<xml></xml>');
        $this->module->seeResponseIsXml();
        $this->setStubResponse('<xml><name>John</name></xml>');
        $this->module->seeResponseIsXml();
        $this->module->seeResponseEquals('<xml><name>John</name></xml>');
    }

    public function testInvalidXml()
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('<xml><name>John</surname></xml>');
        $this->module->seeResponseIsXml();
    }

    public function testSeeInJson()
    {
        $this->setStubResponse(
            '{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}, "labels": null}}'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContainsJson(['name' => 'Davert']);
        $this->module->seeResponseContainsJson(['user' => ['name' => 'Davert']]);
        $this->module->seeResponseContainsJson(['ticket' => ['title' => 'Bug should be fixed']]);
        $this->module->seeResponseContainsJson(['ticket' => ['user' => ['name' => 'Davert']]]);
        $this->module->seeResponseContainsJson(['ticket' => ['labels' => null]]);
    }

    public function testSeeInJsonCollection()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContainsJson(['tags' => ['web-dev', 'java']]);
        $this->module->seeResponseContainsJson(['user' => 'John Doe', 'age' => 27]);
    }

    public function testArrayJson()
    {
        $this->setStubResponse(
            '[{"id":1,"title": "Bug should be fixed"},{"title": "Feature should be implemented","id":2}]'
        );
        $this->module->seeResponseContainsJson(['id' => 1]);
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/4202
     */
    public function testSeeResponseContainsJsonFailsGracefullyWhenJsonResultIsNotArray()
    {
        $this->shouldFail();
        $this->setStubResponse(json_encode('no_status', JSON_THROW_ON_ERROR));
        $this->module->seeResponseContainsJson(['id' => 1]);
    }

    public function testDontSeeResponseJsonMatchesJsonPathPassesWhenJsonResultIsNotArray()
    {
        $this->setStubResponse(json_encode('no_status', JSON_THROW_ON_ERROR));
        $this->module->dontSeeResponseJsonMatchesJsonPath('$.error');
    }

    public function testDontSeeInJson()
    {
        $this->setStubResponse('{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}}}');
        $this->module->seeResponseIsJson();
        $this->module->dontSeeResponseContainsJson(['name' => 'Davet']);
        $this->module->dontSeeResponseContainsJson(['user' => ['name' => 'Davet']]);
        $this->module->dontSeeResponseContainsJson(['user' => ['title' => 'Bug should be fixed']]);
    }

    public function testApplicationJsonIncludesJsonAsContent()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendPOST('/', ['name' => 'john']);
        /** @var $request SymfonyRequest **/
        $request = $this->module->client->getRequest();
        $this->assertContains('application/json', $request->getServer());
        $server = $request->getServer();
        $this->assertEquals('application/json', $server['HTTP_CONTENT_TYPE']);
        $this->assertJson($request->getContent());
        $this->assertEmpty($request->getParameters());
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/3516
     */
    public function testApplicationJsonHeaderCheckIsCaseInsensitive()
    {
        $this->module->haveHttpHeader('content-type', 'application/json');
        $this->module->sendPOST('/', ['name' => 'john']);
        /** @var $request SymfonyRequest  **/
        $request = $this->module->client->getRequest();
        $server = $request->getServer();
        $this->assertEquals('application/json', $server['HTTP_CONTENT_TYPE']);
        $this->assertJson($request->getContent());
        $this->assertEmpty($request->getParameters());
    }

    public function testGetApplicationJsonNotIncludesJsonAsContent()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendGET('/', ['name' => 'john']);
        /** @var $request SymfonyRequest  **/
        $request = $this->module->client->getRequest();
        $this->assertNull($request->getContent());
        $this->assertContains('john', $request->getParameters());
    }

    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2075
     * Client is undefined for the second test
     */
    public function testTwoTests()
    {
        $cest1 = Stub::makeEmpty(Cest::class);
        $cest2 = Stub::makeEmpty(Cest::class);

        $this->module->sendGET('/rest/user/');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContains('davert');
        $this->module->seeResponseContainsJson(['name' => 'davert']);
        $this->module->seeResponseCodeIs(200);
        $this->module->dontSeeResponseCodeIs(404);

        $this->phpBrowser->_after($cest1);
        $this->module->_after($cest1);
        $this->module->_before($cest2);

        $this->phpBrowser->_before($cest2);

        $this->module->sendGET('/rest/user/');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContains('davert');
        $this->module->seeResponseContainsJson(['name' => 'davert']);
        $this->module->seeResponseCodeIs(200);
        $this->module->dontSeeResponseCodeIs(404);
    }
    
    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2070
     */
    public function testArrayOfZeroesInJsonResponse()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendGET('/rest/zeroes');
        $this->module->dontSeeResponseContainsJson([
            'responseCode' => 0,
            'data' => [
                0,
                0,
                0,
            ]
        ]);
    }

    public function testFileUploadWithKeyValueArray()
    {
        $tmpFileName = tempnam('/tmp', 'test_');
        file_put_contents($tmpFileName, 'test data');
        $files = [
            'file' => $tmpFileName,
        ];
        $this->module->sendPOST('/rest/file-upload', [], $files);
        $this->module->seeResponseContainsJson([
            'uploaded' => true,
        ]);
    }

    public function testFileUploadWithFilesArray()
    {
        $tmpFileName = tempnam('/tmp', 'test_');
        file_put_contents($tmpFileName, 'test data');
        $files = [
            'file' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
                'size' => 9,
                'tmp_name' => $tmpFileName,
            ]
        ];
        $this->module->sendPOST('/rest/file-upload', [], $files);
        $this->module->seeResponseContainsJson([
            'uploaded' => true,
        ]);
    }

    public function testCanInspectResultOfPhpBrowserRequest()
    {
        $this->phpBrowser->amOnPage('/rest/user/');
        $this->module->seeResponseCodeIs(200);
        $this->module->seeResponseIsJson();
    }

    /**
     * @Issue 4203 https://github.com/Codeception/Codeception/issues/4203
     */
    public function testSessionHeaderBackup()
    {

        $this->module->haveHttpHeader('foo', 'bar');
        $this->module->sendGET('/rest/foo/');
        $this->module->seeResponseContains('foo: "bar"');

        $session = $this->phpBrowser->_backupSession();

        $this->module->haveHttpHeader('foo', 'baz');
        $this->module->sendGET('/rest/foo/');
        $this->module->seeResponseContains('foo: "baz"');

        $this->phpBrowser->_loadSession($session);
        $this->module->sendGET('/rest/foo/');
        $this->module->seeResponseContains('foo: "bar"');
    }

    protected function shouldFail()
    {
        $this->expectException(AssertionFailedError::class);
    }

    public function testGrabFromCurrentUrl()
    {
        $this->module->sendGET('/rest/foo/');
        $this->assertEquals('/rest/foo/', $this->phpBrowser->grabFromCurrentUrl());
    }
}
