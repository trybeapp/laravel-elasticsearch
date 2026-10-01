<?php

namespace Tests\Unit\Elasticsearch;

use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\QueryBuilder;
use DesignMyNight\Elasticsearch\QueryGrammar;
use DesignMyNight\Elasticsearch\QueryProcessor;
use Mockery as m;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends TestCase
{
    /** @var QueryBuilder */
    private $builder;

    public function setUp(): void
    {
        parent::setUp();

        /** @var Connection|m\MockInterface $connection */
        $connection = m::mock(Connection::class);

        /** @var QueryGrammar|m\MockInterface $queryGrammar */
        $queryGrammar = m::mock(QueryGrammar::class);

        /** @var QueryProcessor|m\MockInterface $queryProcessor */
        $queryProcessor = m::mock(QueryProcessor::class);

        $this->builder = new QueryBuilder($connection, $queryGrammar, $queryProcessor);
    }

    #[Test]
    #[DataProvider('whereParentIdProvider')]
    public function adds_parent_id_to_wheres_clause(string $parentType, $id, string $boolean):void
    {
        $this->builder->whereParentId($parentType, $id, $boolean);

        $this->assertEquals([
            'type' => 'ParentId',
            'parentType' => $parentType,
            'id' => $id,
            'boolean' => $boolean,
        ], $this->builder->wheres[0]);
    }

    /**
     * @return array
     */
    public static function whereParentIdProvider():array
    {
        return [
            'boolean and' => ['my_parent', 1, 'and'],
            'boolean or' => ['my_parent', 1, 'or'],
        ];
    }

    #[Test]
    #[DataProvider('orderDirectionProvider')]
    public function normalizes_order_direction($direction, int $expected): void
    {
        $this->builder->orderBy('col', $direction);

        $this->assertSame($expected, $this->builder->orders[0]['direction']);
    }

    public static function orderDirectionProvider(): array
    {
        $provider = [
            'asc string' => ['asc', 1],
            'desc string' => ['desc', -1],
            'uppercase DESC' => ['DESC', -1],
            'int 1' => [1, 1],
            'int -1' => [-1, -1],
            'backed enum asc' => [OrderDirectionStub::Ascending, 1],
            'backed enum desc' => [OrderDirectionStub::Descending, -1],
            'unit enum desc' => [OrderDirectionUnitStub::Desc, -1],
            'unit enum asc' => [OrderDirectionUnitStub::Asc, 1],
        ];

        if (enum_exists(\Illuminate\Database\Query\SortDirection::class)) {
            $provider['laravel asc'] = [\Illuminate\Database\Query\SortDirection::Ascending, 1];
            $provider['laravel desc'] = [\Illuminate\Database\Query\SortDirection::Descending, -1];
        }

        return $provider;
    }

    #[Test]
    public function order_by_desc_sorts_descending(): void
    {
        $this->builder->orderByDesc('col');

        $this->assertSame(-1, $this->builder->orders[0]['direction']);
    }

    #[Test]
    public function compiles_enum_directions_to_elasticsearch_orders(): void
    {
        $builder = new QueryBuilder(m::mock(Connection::class), new QueryGrammar(), m::mock(QueryProcessor::class));
        $builder->orderBy('a', OrderDirectionStub::Descending)->orderByDesc('b')->orderBy('c', OrderDirectionStub::Ascending);

        $compile = new \ReflectionMethod(QueryGrammar::class, 'compileOrders');
        $compile->setAccessible(true);

        $this->assertSame([
            ['a' => ['order' => 'desc']],
            ['b' => ['order' => 'desc']],
            ['c' => ['order' => 'asc']],
        ], $compile->invoke(new QueryGrammar(), $builder, $builder->orders));
    }
}

enum OrderDirectionStub: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

enum OrderDirectionUnitStub
{
    case Asc;
    case Desc;
}
