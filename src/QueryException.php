<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\QueryException as BaseQueryException;

class QueryException extends BaseQueryException
{
    /**
     * Format the error message.
     *
     * @param  array  $query
     * @param  array  $bindings
     * @param  \Exception $previous
     * @return string
     */
    protected function formatMessage($connectionName, $sql, $bindings, \Throwable $previous)
    {
        return $previous->getMessage();
    }
}
