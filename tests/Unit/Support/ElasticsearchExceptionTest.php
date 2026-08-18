<?php

namespace Tests\Unit\Support;

use DesignMyNight\Elasticsearch\Support\ElasticsearchException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException as BaseElasticsearchException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ElasticsearchExceptionTest extends TestCase
{
    #[Test]
    #[DataProvider('errorMessagesProvider')]
    public function returns_the_error_code(
        BaseElasticsearchException $exception,
        string $code,
        string $message,
        array $raw
    ): void
    {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($code, $exception->getCode());
    }

    #[Test]
    #[DataProvider('errorMessagesProvider')]
    public function returns_the_error_message(
        BaseElasticsearchException $exception,
        string $code,
        string $message,
        array $raw
    ): void {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($message, $exception->getMessage());
    }

    #[Test]
    #[DataProvider('errorMessagesProvider')]
    public function converts_the_error_to_string(
        BaseElasticsearchException $exception,
        string $code,
        string $message,
        array $raw
    ): void {
        $exception = new ElasticsearchException($exception);

        $this->assertSame("$code: $message", (string)$exception);
    }

    #[Test]
    #[DataProvider('errorMessagesProvider')]
    public function returns_the_raw_error_message_as_an_array(
        BaseElasticsearchException $exception,
        string $code,
        string $message,
        array $raw
    ): void
    {
        $exception = new ElasticsearchException($exception);

        $this->assertSame($raw, $exception->getRaw());
    }

    public static function errorMessagesProvider(): array
    {
        $missingIndexError = json_encode(
            [
                "error"  => [
                    "root_cause"    => [
                        [
                            "type"          => "index_not_found_exception",
                            "reason"        => "no such index [bob]",
                            "resource.type" => "index_or_alias",
                            "resource.id"   => "bob",
                            "index_uuid"    => "_na_",
                            "index"         => "bob",
                        ],
                    ],
                    "type"          => "index_not_found_exception",
                    "reason"        => "no such index [bob]",
                    "resource.type" => "index_or_alias",
                    "resource.id"   => "bob",
                    "index_uuid"    => "_na_",
                    "index"         => "bob",
                ],
                "status" => 404,
            ]
        );

        return [
            'missing_index' => [
                new ClientResponseException($missingIndexError, 404),
                'index_not_found_exception',
                'no such index [bob]',
                json_decode($missingIndexError, true),
            ],
        ];
    }
}
