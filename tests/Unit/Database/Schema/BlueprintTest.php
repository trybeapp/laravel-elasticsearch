<?php

namespace Tests\Unit\Database\Schema;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use DesignMyNight\Elasticsearch\Support\SchemaCompatibility;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class BlueprintTest extends TestCase
{
    /** @var Blueprint */
    private $blueprint;

    public function setUp(): void
    {
        parent::setUp();

        $this->blueprint = SchemaCompatibility::blueprintExpectsConnection()
            ? new Blueprint($this->newConnection(), 'indices')
            : new Blueprint('indices');
    }

    /**
     * Laravel 12 blueprints resolve their grammar from the connection they are
     * given, so one is needed here. The real constructor is skipped so no
     * Elasticsearch server is required.
     *
     * @return Connection
     */
    private function newConnection(): Connection
    {
        return new class extends Connection
        {
            public function __construct()
            {
                // Skip the real constructor to avoid requiring an Elasticsearch server
            }
        };
    }

    /**
     * It gets the index alias.
     *
     * @covers       \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getAlias
     */
    #[Test]
    #[DataProvider('get_alias_data_provider')]
    public function it_gets_the_index_alias(string $expected, $alias = null)
    {
        if (isset($alias)) {
            $this->blueprint->alias($alias);
        }

        $this->assertEquals($expected, $this->blueprint->getAlias());
    }

    /**
     * getAlias data provider.
     */
    public static function get_alias_data_provider(): array
    {
        return [
            'alias not provided' => ['indices_dev'],
            'alias provided'     => ['alias_dev', 'alias'],
        ];
    }

    /**
     * It gets the document type.
     *
     * @covers       \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getDocumentType
     */
    #[Test]
    #[DataProvider('get_document_type_data_provider')]
    public function it_gets_the_document_type(string $expected, $documentType = null)
    {
        if (isset($documentType)) {
            $this->blueprint->document($documentType);
        }

        $this->assertEquals($expected, $this->blueprint->getDocumentType());
    }

    /**
     * getDocumentType data provider.
     */
    public static function get_document_type_data_provider(): array
    {
        return [
            'document not provided' => ['index'],
            'document provided'     => ['document', 'document'],
        ];
    }

    /**
     * It generates an index name.
     *
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Blueprint::getIndex
     */
    #[Test]
    public function it_generates_an_index_name()
    {
        Carbon::setTestNow(Carbon::create(2019, 7, 2, 12));

        $this->assertEquals('2019_07_02_120000_indices_dev', $this->blueprint->getIndex());
    }

    /**
     * adds settings ready to be used
     */
    #[Test]
    public function adds_settings_ready_to_be_used():void
    {
        $settings = [
            'filter' => [
                'autocomplete_filter' => [
                    'type'     => 'edge_ngram',
                    'min_gram' => 1,
                    'max_gram' => 20,
                ],
            ],
            'analyzer' => [
                'autocomplete' => [
                    'type'      => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'autocomplete_filter',
                    ],
                ],
            ],
        ];

        $this->blueprint->addIndexSettings('analysis', $settings);

        $this->assertSame(
            [
                'analysis' => $settings,
            ],
            $this->blueprint->getIndexSettings()
        );
    }
}
