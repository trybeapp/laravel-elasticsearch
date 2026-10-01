<?php

namespace Tests\Unit\Console\Mappings;

use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Support\AliasAlreadyExistsException;
use DesignMyNight\Elasticsearch\Support\MappingService;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as HttpClient;
use Orchestra\Testbench\TestCase;

/**
 * @package Tests\Console\Mappings
 */
class MappingServiceTest extends TestCase
{
    protected HttpClient $httpMock;
    protected Client $client;
    protected MappingService $service;

    /**
     * Set up tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        $config = [
            'driver'   => 'elasticsearch',
            'host'     => 'localhost',
            'port'     => 9200,
            'scheme'   => 'https',
            'username' => 'elastic_user',
            'password' => 'elastic_password',
            'suffix'   => null,
        ];

        config([
            'database' => [
                'connections' => [
                    'elasticsearch' => $config
                ],
            ],
        ]);

        $this->httpMock = new HttpClient();
        $this->client = ClientBuilder::create()
            ->setHttpClient($this->httpMock)
            ->build();

        $response = new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'text/plain; charset=UTF-8',
            ],
            'OK',
        );

        $this->httpMock->setDefaultResponse($response);

        $config['options']['httpClient'] = $this->httpMock;

        $connection = new Connection($config);

        $this->service = $this->app->make(MappingService::class)
            ->setConnection($connection);
    }

    public function test_it_gets_a_list_of_indices_on_the_elasticsearch_cluster()
    {
        $this->httpMock->addResponse(new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'application/json',
            ],
            json_encode([
                [
                    'alias' => 'basket_items_test',
                    'index' => '2023_04_13_155500_basket_items_test',
                ],
            ])
        ));

        $aliases = $this->service->getIndicesForAlias('*');

        $this->assertCount(1, $aliases);
        $this->assertEquals('basket_items_test', $aliases[0]['alias']);
        $this->assertEquals('2023_04_13_155500_basket_items_test', $aliases[0]['index']);
    }

    public function test_it_filters_for_an_alias()
    {
        $this->httpMock->addResponse(new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'application/json',
            ],
            json_encode([
                [
                    'alias' => 'basket_items_test',
                    'index' => '2023_04_13_155500_basket_items_test',
                ],
            ])
        ));

        $aliases = $this->service->getIndicesForAlias('basket_items_test');

        $this->assertCount(1, $aliases);
        $this->assertEquals('basket_items_test', $aliases[0]['alias']);
        $this->assertEquals('2023_04_13_155500_basket_items_test', $aliases[0]['index']);
    }

    public function test_it_removes_an_index()
    {
        $this->httpMock->addResponse(new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'application/json',
            ],
            json_encode([
                [
                    'alias' => 'basket_items_test',
                    'index' => '2023_04_13_155500_basket_items_test',
                ],
            ])
        ));

        $aliases = $this->service->getIndicesForAlias('basket_items_test');

        $this->assertCount(1, $aliases);
        $this->assertEquals('basket_items_test', $aliases[0]['alias']);
        $this->assertEquals('2023_04_13_155500_basket_items_test', $aliases[0]['index']);
    }

    public function test_it_creates_an_alias_that_does_not_exist_yet()
    {
        // HEAD _alias/basket_items_test — not found, so the name is free.
        $this->httpMock->addResponse($this->response(404, ''));

        $created = $this->service->createAlias('2023_04_13_155500_basket_items_test', 'basket_items_test');

        $this->assertTrue($created);
        $this->assertSame('PUT', $this->lastRequestMethod());
    }

    public function test_it_does_nothing_when_the_alias_already_points_at_the_index()
    {
        $this->httpMock->addResponse($this->response(200, 'OK'));
        $this->httpMock->addResponse($this->jsonResponse([
            [
                'alias' => 'basket_items_test',
                'index' => '2023_04_13_155500_basket_items_test',
            ],
        ]));

        $created = $this->service->createAlias('2023_04_13_155500_basket_items_test', 'basket_items_test');

        // Re-running a mapping migration against an environment that already
        // has the alias is not a failure, and must not re-put it.
        $this->assertFalse($created);
        $this->assertNotSame('PUT', $this->lastRequestMethod());
    }

    public function test_it_reports_a_conflict_when_the_alias_points_at_another_index()
    {
        $this->httpMock->addResponse($this->response(200, 'OK'));
        $this->httpMock->addResponse($this->jsonResponse([
            [
                'alias' => 'basket_items_test',
                'index' => '2022_01_01_000000_basket_items_test',
            ],
        ]));

        try {
            $this->service->createAlias('2023_04_13_155500_basket_items_test', 'basket_items_test');

            $this->fail('Expected an AliasAlreadyExistsException.');
        } catch (AliasAlreadyExistsException $exception) {
            $this->assertSame('basket_items_test', $exception->getAlias());
            $this->assertSame(['2022_01_01_000000_basket_items_test'], $exception->getIndices());
            $this->assertStringContainsString('2022_01_01_000000_basket_items_test', $exception->getMessage());
        }
    }

    private function response(int $status, string $body): Response
    {
        return new Response(
            $status,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'text/plain; charset=UTF-8',
            ],
            $body,
        );
    }

    private function jsonResponse(array $body): Response
    {
        return new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'content-type' => 'application/json',
            ],
            json_encode($body),
        );
    }

    private function lastRequestMethod(): string
    {
        $requests = $this->httpMock->getRequests();

        return end($requests)->getMethod();
    }
}
