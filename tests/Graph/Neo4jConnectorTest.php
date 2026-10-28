<?php

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Authentication\OpenIDConnectAuth;
use Voyager\Graph\Database\Connectors\Neo4jConnector;

beforeEach(function () {
    if (! class_exists(Authenticate::class)) {
        $this->markTestSkipped('Needs laudis/neo4j-php-client.');
    }
});

it('logs in with a token through the client\'s bearer-scheme auth', function () {
    $connector = new class extends Neo4jConnector
    {
        public function auth(array $config): object
        {
            return $this->buildAuthentication($config);
        }
    };

    expect($connector->auth(['token' => 'a-token']))->toBeInstanceOf(OpenIDConnectAuth::class);
});
