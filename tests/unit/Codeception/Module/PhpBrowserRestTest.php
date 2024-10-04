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
    private REST $rest;

    private ?PhpBrowser $phpBrowser = null;

    protected function _setUp(): void
    {
        $container = Stub::make(ModuleContainer::class);
        $this->phpBrowser = new PhpBrowser($container);
        $url = 'http://localhost:8010';
        $this->phpBrowser->_setConfig(['url' => $url]);
        $this->phpBrowser->_initialize();

        $this->rest = Stub::make(REST::class);
        $this->rest->_inject($this->phpBrowser);
        $this->rest->_initialize();
        $this->rest->_before(Stub::makeEmpty(Cest::class));

        $this->phpBrowser->_before(Stub::makeEmpty(Cest::class));
    }

    private function setStubResponse($response): void
    {
        $this->phpBrowser = Stub::make(PhpBrowser::class, ['_getResponseContent' => $response]);
        $this->rest->_inject($this->phpBrowser);
        $this->rest->_initialize();
        $this->rest->_before(Stub::makeEmpty(Cest::class));
    }

    public function testGet(): void
    {
        $this->rest->sendGET('/rest/user/');
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseContains('davert');
        $this->rest->seeResponseContainsJson(['name' => 'davert']);
        $this->rest->seeResponseCodeIs(200);
        $this->rest->dontSeeResponseCodeIs(404);
    }

    public function testSendAbsoluteUrlGet(): void
    {
        $this->rest->sendGET('http://127.0.0.1:8010/rest/user/');
        $this->rest->seeResponseCodeIs(200);
    }

    public function testPost(): void
    {
        $this->rest->sendPOST('/rest/user/', ['name' => 'john']);
        $this->rest->seeResponseContains('john');
        $this->rest->seeResponseContainsJson(['name' => 'john']);
    }

    public function testValidJson(): void
    {
        $this->setStubResponse('{"xxx": "yyy"}');
        $this->rest->seeResponseIsJson();
        $this->setStubResponse('{"xxx": "yyy", "zzz": ["a","b"]}');
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseEquals('{"xxx": "yyy", "zzz": ["a","b"]}');
    }

    public function testInvalidJson(): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('{xxx = yyy}');
        $this->rest->seeResponseIsJson();
    }

    public function testValidXml(): void
    {
        $this->setStubResponse('<xml></xml>');
        $this->rest->seeResponseIsXml();
        $this->setStubResponse('<xml><name>John</name></xml>');
        $this->rest->seeResponseIsXml();
        $this->rest->seeResponseEquals('<xml><name>John</name></xml>');
    }

    public function testInvalidXml(): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('<xml><name>John</surname></xml>');
        $this->rest->seeResponseIsXml();
    }

    public function testSeeInJson(): void
    {
        $this->setStubResponse(
            '{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}, "labels": null}}'
        );
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseContainsJson(['name' => 'Davert']);
        $this->rest->seeResponseContainsJson(['user' => ['name' => 'Davert']]);
        $this->rest->seeResponseContainsJson(['ticket' => ['title' => 'Bug should be fixed']]);
        $this->rest->seeResponseContainsJson(['ticket' => ['user' => ['name' => 'Davert']]]);
        $this->rest->seeResponseContainsJson(['ticket' => ['labels' => null]]);
    }

    public function testSeeInJsonCollection(): void
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseContainsJson(['tags' => ['web-dev', 'java']]);
        $this->rest->seeResponseContainsJson(['user' => 'John Doe', 'age' => 27]);
    }

    public function testArrayJson(): void
    {
        $this->setStubResponse(
            '[{"id":1,"title": "Bug should be fixed"},{"title": "Feature should be implemented","id":2}]'
        );
        $this->rest->seeResponseContainsJson(['id' => 1]);
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/4202
     */
    public function testSeeResponseContainsJsonFailsGracefullyWhenJsonResultIsNotArray(): void
    {
        $this->shouldFail();
        $this->setStubResponse(json_encode('no_status', JSON_THROW_ON_ERROR));
        $this->rest->seeResponseContainsJson(['id' => 1]);
    }

    public function testDontSeeResponseJsonMatchesJsonPathPassesWhenJsonResultIsNotArray(): void
    {
        $this->setStubResponse(json_encode('no_status', JSON_THROW_ON_ERROR));
        $this->rest->dontSeeResponseJsonMatchesJsonPath('$.error');
    }

    public function testDontSeeInJson(): void
    {
        $this->setStubResponse('{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}}}');
        $this->rest->seeResponseIsJson();
        $this->rest->dontSeeResponseContainsJson(['name' => 'Davet']);
        $this->rest->dontSeeResponseContainsJson(['user' => ['name' => 'Davet']]);
        $this->rest->dontSeeResponseContainsJson(['user' => ['title' => 'Bug should be fixed']]);
    }

    public function testApplicationJsonIncludesJsonAsContent(): void
    {
        $this->rest->haveHttpHeader('Content-Type', 'application/json');
        $this->rest->sendPOST('/', ['name' => 'john']);
        /** @var $request SymfonyRequest **/
        $request = $this->rest->client->getRequest();
        $this->assertContains('application/json', $request->getServer());
        $server = $request->getServer();
        $this->assertEquals('application/json', $server['HTTP_CONTENT_TYPE']);
        $this->assertJson($request->getContent());
        $this->assertEmpty($request->getParameters());
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/3516
     */
    public function testApplicationJsonHeaderCheckIsCaseInsensitive(): void
    {
        $this->rest->haveHttpHeader('content-type', 'application/json');
        $this->rest->sendPOST('/', ['name' => 'john']);
        /** @var $request SymfonyRequest  **/
        $request = $this->rest->client->getRequest();
        $server = $request->getServer();
        $this->assertEquals('application/json', $server['HTTP_CONTENT_TYPE']);
        $this->assertJson($request->getContent());
        $this->assertEmpty($request->getParameters());
    }

    public function testGetApplicationJsonNotIncludesJsonAsContent(): void
    {
        $this->rest->haveHttpHeader('Content-Type', 'application/json');
        $this->rest->sendGET('/', ['name' => 'john']);
        /** @var $request SymfonyRequest  **/
        $request = $this->rest->client->getRequest();
        $this->assertNull($request->getContent());
        $this->assertContains('john', $request->getParameters());
    }

    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2075
     * Client is undefined for the second test
     */
    public function testTwoTests(): void
    {
        $cest1 = Stub::makeEmpty(Cest::class);
        $cest2 = Stub::makeEmpty(Cest::class);

        $this->rest->sendGET('/rest/user/');
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseContains('davert');
        $this->rest->seeResponseContainsJson(['name' => 'davert']);
        $this->rest->seeResponseCodeIs(200);
        $this->rest->dontSeeResponseCodeIs(404);

        $this->phpBrowser->_after($cest1);
        $this->rest->_after($cest1);
        $this->rest->_before($cest2);

        $this->phpBrowser->_before($cest2);

        $this->rest->sendGET('/rest/user/');
        $this->rest->seeResponseIsJson();
        $this->rest->seeResponseContains('davert');
        $this->rest->seeResponseContainsJson(['name' => 'davert']);
        $this->rest->seeResponseCodeIs(200);
        $this->rest->dontSeeResponseCodeIs(404);
    }

    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2070
     */
    public function testArrayOfZeroesInJsonResponse(): void
    {
        $this->rest->haveHttpHeader('Content-Type', 'application/json');
        $this->rest->sendGET('/rest/zeroes');
        $this->rest->dontSeeResponseContainsJson([
            'responseCode' => 0,
            'data' => [
                0,
                0,
                0,
            ]
        ]);
    }

    public function testFileUploadWithKeyValueArray(): void
    {
        $tmpFileName = tempnam('/tmp', 'test_');
        file_put_contents($tmpFileName, 'test data');
        $files = [
            'file' => $tmpFileName,
        ];
        $this->rest->sendPOST('/rest/file-upload', [], $files);
        $this->rest->seeResponseContainsJson([
            'uploaded' => true,
        ]);
    }

    public function testFileUploadWithFilesArray(): void
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
        $this->rest->sendPOST('/rest/file-upload', [], $files);
        $this->rest->seeResponseContainsJson([
            'uploaded' => true,
        ]);
    }

    public function testMultipartPostPreservesArrayOrder(): void
    {
        $tmpFileName = tempnam('/tmp', 'test_');
        file_put_contents($tmpFileName, 'test data');
        $body = [
            'users' => [
                ['id' => 0, 'name' => 'John Doe'],
                ['id' => 1, 'name' => 'Jane Doe'],
            ]
        ];
        $files = [
            'file' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
                'size' => 9,
                'tmp_name' => $tmpFileName,
            ]
        ];
        $this->rest->sendPOST('/rest/multipart-collections', $body, $files);
        $this->rest->seeResponseEquals(json_encode([
            'body' => [
                'users' => [
                    '0' => ['id' => '0', 'name' => 'John Doe'],
                    '1' => ['id' => '1', 'name' => 'Jane Doe'],
                ],
            ],
        ]));
    }

    public function testCanInspectResultOfPhpBrowserRequest(): void
    {
        $this->phpBrowser->amOnPage('/rest/user/');
        $this->rest->seeResponseCodeIs(200);
        $this->rest->seeResponseIsJson();
    }

    /**
     * @Issue 4203 https://github.com/Codeception/Codeception/issues/4203
     */
    public function testSessionHeaderBackup(): void
    {

        $this->rest->haveHttpHeader('foo', 'bar');
        $this->rest->sendGET('/rest/foo/');
        $this->rest->seeResponseContains('foo: "bar"');

        $session = $this->phpBrowser->_backupSession();

        $this->rest->haveHttpHeader('foo', 'baz');
        $this->rest->sendGET('/rest/foo/');
        $this->rest->seeResponseContains('foo: "baz"');

        $this->phpBrowser->_loadSession($session);
        $this->rest->sendGET('/rest/foo/');
        $this->rest->seeResponseContains('foo: "bar"');
    }

    private function shouldFail(): void
    {
        $this->expectException(AssertionFailedError::class);
    }

    public function testGrabFromCurrentUrl(): void
    {
        $this->rest->sendGET('/rest/foo/');
        $this->assertEquals('/rest/foo/', $this->phpBrowser->grabFromCurrentUrl());
    }
}
