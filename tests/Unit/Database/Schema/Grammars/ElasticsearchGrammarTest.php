<?php

namespace Tests\Unit\Database\Schema\Grammars;

use Carbon\Carbon;
use Closure;
use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar as SchemaGrammar;
use DesignMyNight\Elasticsearch\QueryBuilder;
use DesignMyNight\Elasticsearch\QueryGrammar;
use DesignMyNight\Elasticsearch\QueryProcessor as ElasticsearchQueryProcessor;
use DesignMyNight\Elasticsearch\Support\SchemaCompatibility;
use Elastic\Elasticsearch\ClientInterface;
use Elastic\Elasticsearch\Namespaces\CatNamespace;
use Elastic\Elasticsearch\Namespaces\IndicesNamespace;
use Illuminate\Support\Fluent;
use Mockery as m;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ElasticsearchGrammarTest extends TestCase
{
    /** @var Blueprint|m\CompositeExpectation */
    private $blueprint;

    /** @var Connection|m\CompositeExpectation */
    private $connection;

    /** @var QueryGrammar */
    private $grammar;

    /** @var SchemaGrammar */
    private $schemaGrammar;

    /** @var ElasticsearchQueryProcessor */
    private $processor;

    public function setUp(): void
    {
        parent::setUp();

        /** @var CatNamespace|m\CompositeExpectation $catNamespace */
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([]);

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('existsAlias')->andReturnFalse();

        /** @var ClientInterface|m\CompositeExpectation $client */
        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('cat')->andReturn($catNamespace);
        $client->shouldReceive('indices')->andReturn($indicesNamespace);

        /** @var Connection|m\CompositeExpectation $connection */
        $connection = m::mock(Connection::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $connection->shouldReceive('createConnection')->andReturn($client);

        Carbon::setTestNow(
            Carbon::create(2019, 7, 2, 12)
        );

        $this->connection = $connection;
        $this->blueprint = SchemaCompatibility::blueprintExpectsConnection()
            ? new Blueprint($connection, 'indices')
            : new Blueprint('indices');
        $this->schemaGrammar = SchemaCompatibility::grammarExpectsConnection()
            ? new SchemaGrammar($connection)
            : new SchemaGrammar();
        $this->grammar = SchemaCompatibility::grammarExpectsConnection()
            ? new QueryGrammar($connection)
            : new QueryGrammar();
        $this->processor = new ElasticsearchQueryProcessor();
        $this->builder = new QueryBuilder($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Laravel 12 blueprints resolve their grammar from the connection they are
     * given, so one is needed there but must not be passed on 10 or 11.
     *
     * @param string $table
     *
     * @return Blueprint
     */
    private function newBlueprint(string $table): Blueprint
    {
        return SchemaCompatibility::blueprintExpectsConnection()
            ? new Blueprint($this->connection, $table)
            : new Blueprint($table);
    }

    /**
     * It returns a closure that will create an index.
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::compileCreate
     */
    #[Test]
    public function it_returns_a_closure_that_will_create_an_index()
    {
        $alias = 'indices_dev';
        $index = '2019_07_02_120000_indices_dev';
        $mapping = [
            'mappings' => [
                'properties' => [
                    'title' => [
                        'type' => 'text',
                        'fields' => [
                            'raw' => [
                                'type' => 'keyword'
                            ]
                        ]
                    ],
                    'date' => [
                        'type' => 'date'
                    ]
                ]
            ]
        ];
        $blueprint = clone($this->blueprint);

        $blueprint->text('title')->fields(function (Blueprint $mapping): void {
            $mapping->keyword('raw');
        });
        $blueprint->date('date');

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('create')->once()->with(['index' => $index, 'body' => $mapping]);
        $indicesNamespace->shouldReceive('existsAlias')->once()->with(['name' => $alias])->andReturnFalse();
        $indicesNamespace->shouldReceive('putAlias')->once()->with(['index' => $index, 'name' => $alias]);

        $this->connection->shouldReceive('indices')->andReturn($indicesNamespace);
        $this->connection->shouldReceive('createAlias')->once()->with($index, $alias)->passthru();

        $this->connection->shouldReceive('createIndex')->once()->with($index, $mapping)->passthru();

        $executable = $this->schemaGrammar->compileCreate($this->newBlueprint(''), new Fluent(), $this->connection);

        $this->assertInstanceOf(Closure::class, $executable);

        $executable($blueprint, $this->connection);
    }

    /**
     * It returns a closure that will drop an index.
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::compileDrop
     */
    #[Test]
    public function it_returns_a_closure_that_will_drop_an_index()
    {
        $index = '2019_06_03_120000_indices_dev';

        /** @var CatNamespace|m\CompositeExpectation $catNamespace */
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([
            ['index' => $index]
        ]);

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('delete')->once()->with(['index' => $index]);

        $this->connection->shouldReceive('cat')->andReturn($catNamespace);
        $this->connection->shouldReceive('indices')->andReturn($indicesNamespace);
        $this->connection->shouldReceive('dropIndex')->once()->with($index)->passthru();

        $executable = $this->schemaGrammar->compileDrop($this->newBlueprint(''), new Fluent(), $this->connection);

        $this->assertInstanceOf(Closure::class, $executable);

        $executable($this->blueprint, $this->connection);
    }

    /**
     * It returns a closure that will drop an index if it exists.
     * @covers       \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::compileDropIfExists
     */
    #[Test]
    #[DataProvider('compile_drop_if_exists_data_provider')]
    public function it_returns_a_closure_that_will_drop_an_index_if_it_exists($table, $times)
    {
        $index = '2019_06_03_120000_indices_dev';
        $this->blueprint = $this->newBlueprint($table);

        /** @var CatNamespace|m\CompositeExpectation $catNamespace */
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([
            ['index' => $index]
        ]);

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('delete')->times($times)->with(['index' => $index]);

        $this->connection->shouldReceive('indices')->andReturn($indicesNamespace);
        $this->connection->shouldReceive('cat')->once()->andReturn($catNamespace);
        $this->connection->shouldReceive('dropIndex')->times($times)->with($index)->passthru();

        $executable = $this->schemaGrammar->compileDropIfExists($this->newBlueprint(''), new Fluent(), $this->connection);

        $this->assertInstanceOf(Closure::class, $executable);

        $executable($this->blueprint, $this->connection);
    }

    /**
     * compileDropIfExists data provider.
     */
    public static function compile_drop_if_exists_data_provider(): array
    {
        return [
            'it exists' => ['indices', 1],
            'it does not exists' => ['books', 0]
        ];
    }

    /**
     * It returns a closure that will update an index mapping.
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::compileUpdate
     */
    #[Test]
    public function it_returns_a_closure_that_will_update_an_index_mapping()
    {
        $this->blueprint->text('title');
        $this->blueprint->date('date');
        $this->blueprint->keyword('status');

        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('putMapping');

        $this->connection->shouldReceive('indices')->andReturn($indicesNamespace);

        $this->connection->shouldReceive('updateIndex')->once()->withArgs(['indices_dev', [
            'properties' => [
                'title' => [
                    'type' => 'text'
                ],
                'date' => [
                    'type' => 'date'
                ],
                'status' => [
                    'type' => 'keyword'
                ]
            ]
        ]]);

        $executable = $this->schemaGrammar->compileUpdate($this->newBlueprint(''), new Fluent(), $this->connection);

        $this->assertInstanceOf(Closure::class, $executable);

        $executable($this->blueprint, $this->connection);
    }

    /**
     * It generates a mapping.
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::getColumns
     */
    #[Test]
    public function it_generates_a_mapping()
    {
        $this->blueprint->join('joins', ['parent' => 'child']);
        $this->blueprint->text('title')->fields(function (Blueprint $field) {
            $field->keyword('raw');
        });
        $this->blueprint->date('start_date');
        $this->blueprint->boolean('is_closed');
        $this->blueprint->keyword('status');
        $this->blueprint->float('price');
        $this->blueprint->integer('total_reviews');
        $this->blueprint->object('location')->properties(function (Blueprint $mapping) {
            $mapping->text('address');
            $mapping->text('postcode');
            $mapping->geoPoint('coordinates');
        });

        $expected = [
            'joins' => [
                'type' => 'join',
                'relations' => [
                    'parent' => 'child'
                ],
            ],
            'title' => [
                'type' => 'text',
                'fields' => [
                    'raw' => [
                        'type' => 'keyword'
                    ]
                ]
            ],
            'start_date' => [
                'type' => 'date'
            ],
            'is_closed' => [
                'type' => 'boolean'
            ],
            'status' => [
                'type' => 'keyword'
            ],
            'price' => [
                'type' => 'float'
            ],
            'total_reviews' => [
                'type' => 'integer'
            ],
            'location' => [
                'properties' => [
                    'address' => [
                        'type' => 'text',
                    ],
                    'postcode' => [
                        'type' => 'text'
                    ],
                    'coordinates' => [
                        'type' => 'geo_point'
                    ]
                ]
            ]
        ];

        $grammar = new class($this->connection) extends SchemaGrammar
        {
            public function outputMapping(Blueprint $blueprint)
            {
                return $this->getColumns($blueprint);
            }
        };

        $this->assertEquals($expected, $grammar->outputMapping($this->blueprint));
    }

    #[Test]
    public function it_compiles_a_where_not_query(): void
    {
        $this->builder->whereNot(function ($builder) {
            $builder->where('field', 'value');
        });

        $this->assertEquals([
            'index' => '',
            'body' => [
                '_source' => true,
                'query' => [
                    'bool' => [
                        'must_not' => [
                            [
                                'bool' => [
                                    'must' => [
                                        [
                                            'term' => [
                                                'field' => 'value',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->grammar->compileSelect($this->builder));
    }
}
