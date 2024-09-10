<?php

namespace DesignMyNight\Elasticsearch\Support;

use Elastic\Elasticsearch\Exceptions\ElasticsearchException as BaseElasticsearchException;
use Exception;

class ElasticsearchException extends Exception
{
    /** @var array */
    private $raw = [];

    /**
     * ElasticsearchException constructor.
     *
     * @param BaseElasticsearchException $exception
     */
    public function __construct(ElasticsearchException $exception)
    {
        $this->parseException($exception);
    }

    /**
     * @return array
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getCode()}: {$this->getMessage()}";
    }

    /**
     * @param BaseElasticsearchException $exception
     */
    private function parseException(BaseElasticsearchException $exception): void
    {
        $body = json_decode($exception->getMessage(), true);

        $this->message = $body['error']['reason'];
        $this->code = $body['error']['type'];

        $this->raw = $body;
    }
}
