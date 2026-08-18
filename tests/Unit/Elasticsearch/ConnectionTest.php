<?php

namespace Tests\Unit\Elasticsearch;

use DesignMyNight\Elasticsearch\Connection;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new class extends Connection
        {
            public function __construct()
            {
                // Skip the real constructor to avoid requiring an Elasticsearch server
            }

            public function replaceQueryBindings(array $query): array
            {
                return parent::replaceQueryBindings($query);
            }
        };
    }

    #[Test]
    public function it_only_replaces_values_inside_the_body(): void
    {
        $query = [
            'index' => 'basket_items',
            'size' => 10000,
            'body' => [
                'query' => [
                    'term' => ['status' => 'active'],
                ],
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals('basket_items', $result['index']);
        $this->assertEquals(10000, $result['size']);
        $this->assertEquals('?', $result['body']['query']['term']['status']);
    }

    #[Test]
    public function it_preserves_field_names_in_dsl_clauses(): void
    {
        $query = [
            'index' => 'basket_items',
            'body' => [
                'query' => [
                    'bool' => [
                        'must_not' => [
                            ['exists' => ['field' => 'deleted_at']],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals('deleted_at', $result['body']['query']['bool']['must_not'][0]['exists']['field']);
    }

    #[Test]
    public function it_preserves_the_source_projection(): void
    {
        $query = [
            'index' => 'basket_items',
            'body' => [
                '_source' => ['_id', 'status', 'created_at'],
                'query' => [
                    'term' => ['booking_id' => '69b18289d4cf42fc8f05f9d3'],
                ],
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals(['_id', 'status', 'created_at'], $result['body']['_source']);
        $this->assertEquals('?', $result['body']['query']['term']['booking_id']);
    }

    #[Test]
    public function it_replaces_term_query_values(): void
    {
        $query = [
            'index' => 'basket_items',
            'body' => [
                '_source' => ['_id'],
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['booking_id' => '69b18289d4cf42fc8f05f9d3']],
                            [
                                'bool' => [
                                    'must_not' => [
                                        ['exists' => ['field' => 'deleted_at']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'size' => 10000,
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals(['_id'], $result['body']['_source']);
        $this->assertEquals('?', $result['body']['query']['bool']['must'][0]['term']['booking_id']);
        $this->assertEquals('deleted_at', $result['body']['query']['bool']['must'][1]['bool']['must_not'][0]['exists']['field']);
        $this->assertEquals('?', $result['body']['size']);
    }

    #[Test]
    public function it_preserves_index_name_in_bulk_action_descriptors(): void
    {
        $query = [
            'body' => [
                ['index' => ['_index' => 'basket_items', '_id' => '69de087897e4a0f5640da224']],
                ['id' => '69de087897e4a0f5640da224', 'status' => 'cancelled', 'price' => 4000],
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals('basket_items', $result['body'][0]['index']['_index']);
        $this->assertEquals('?', $result['body'][0]['index']['_id']);
    }

    #[Test]
    public function it_replaces_document_field_values_in_bulk_body(): void
    {
        $query = [
            'body' => [
                ['index' => ['_index' => 'basket_items', '_id' => '69de087897e4a0f5640da224']],
                [
                    'id' => '69de087897e4a0f5640da224',
                    'status' => 'cancelled',
                    'price' => 4000,
                    'customer' => ['first_name' => 'Test', 'email' => 'test@example.com'],
                ],
            ],
        ];

        $result = $this->connection->replaceQueryBindings($query);

        $document = $result['body'][1];

        $this->assertEquals('?', $document['id']);
        $this->assertEquals('?', $document['status']);
        $this->assertEquals('?', $document['price']);
        $this->assertEquals('?', $document['customer']['first_name']);
        $this->assertEquals('?', $document['customer']['email']);
    }

    #[Test]
    public function it_returns_query_unchanged_when_there_is_no_body(): void
    {
        $query = ['index' => 'basket_items', 'scroll_id' => 'abc123'];

        $result = $this->connection->replaceQueryBindings($query);

        $this->assertEquals($query, $result);
    }
}
