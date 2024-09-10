<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class QueryProcessor extends BaseProcessor
{
    protected $rawResponse;

    protected $aggregations;

    /**
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * Process the results of a "select" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $results)
    {
        $this->rawResponse = $results;

        $this->aggregations = $results['aggregations'] ?? [];

        $this->query = $query;

        $documents = [];

        foreach ($results['hits']['hits'] as $result) {
            $documents[] = $this->documentFromResult($query, $result);
        }

        return $documents;
    }

    /**
     * Process an  "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $response = $query->getConnection()->insert($sql);

        return $response['items'][0]['index']['_id'];
    }

    /**
     * Create a document from the given result
     *
     * @param  Builder $query
     * @param  array $result
     * @return array
     */
    public function documentFromResult(Builder $query, array $result): array
    {
        $document = $result['_source'];
        $document['_id'] = $result['_id'];

        if ($query->includeInnerHits && isset($result['inner_hits'])) {
            $document = $this->addInnerHitsToDocument($document, $result['inner_hits']);
        }

        return $document;
    }

    /**
     * Add inner hits to a document
     *
     * @param  array $document
     * @param  array $innerHits
     * @return array
     */
    protected function addInnerHitsToDocument($document, $innerHits): array
    {
        foreach ($innerHits as $documentType => $hitResults) {
            foreach ($hitResults['hits']['hits'] as $result) {
                $document['inner_hits'][$documentType][] = array_merge(['_id' => $result['_id']], $result['_source']);
            }
        }

        return $document;
    }

    /**
     * Get the raw Elasticsearch response
     *
     * @param array
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse->asArray();
    }

    /**
     * Get the raw aggregation results
     *
     * @param array
     */
    public function getAggregationResults(): array
    {
        return $this->aggregations;
    }
}
