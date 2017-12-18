<?php

use Slim\Http\Environment;
use Slim\Http\Request;
use TinkBank\App;
use TinkBank\Endpoints;


class AccountsTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->app = (new App())->get();
    }

    public function testOpenAccount() {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => Endpoints::V1_ACCOUNTS,
            'CONTENT_TYPE'   => 'application/x-www-form-urlencoded'
        ]);
        $req = Request::createFromEnvironment($env)->withParsedBody([
            'holderName' => 'tinkbanker 1',
            'deposit' => 100
        ]);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $jsonResponse = json_decode($response->getBody(), true);

        $this->assertSame($response->getStatusCode(), 201);
        $this->assertArrayHasKey('id', $jsonResponse);
        $this->assertArrayHasKey('balance', $jsonResponse);
        $this->assertArrayHasKey('holderId', $jsonResponse);
        $this->assertArrayHasKey('holderName', $jsonResponse);
        $this->assertArrayHasKey('createTs', $jsonResponse);
        $this->assertArrayHasKey('status', $jsonResponse);
    } 

    public function testGetBalance_Success() {
        $accountId = 1;
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => Endpoints::V1_ACCOUNTS . '/' . $accountId,
        ]);
        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        
        $jsonResponse = json_decode($response->getBody(), true);

        $this->assertSame($response->getStatusCode(), 200);
        $this->assertArrayHasKey('id', $jsonResponse);
        $this->assertArrayHasKey('balance', $jsonResponse);
        $this->assertArrayHasKey('holderId', $jsonResponse);
        $this->assertArrayHasKey('holderName', $jsonResponse);
        $this->assertArrayHasKey('createTs', $jsonResponse);
        $this->assertArrayHasKey('status', $jsonResponse);
    }

    public function testGetBalance_NotFound() {
        $accountId = 34254365465;
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => Endpoints::V1_ACCOUNTS . '/' . $accountId,
        ]);
        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        
        $jsonResponse = json_decode($response->getBody(), true);

        $this->assertSame($response->getStatusCode(), 404);
        $this->assertArrayHasKey('error', $jsonResponse);
        $this->assertArrayHasKey('code', $jsonResponse['error']);
        $this->assertArrayHasKey('message', $jsonResponse['error']);
    }
}

?>
