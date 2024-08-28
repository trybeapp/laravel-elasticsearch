<?php

namespace DesignMyNight\Elasticsearch;

use Closure;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use DesignMyNight\Elasticsearch\Database\Schema\ElasticsearchBuilder;
use DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Grammar as BaseGrammar;
use Illuminate\Support\Arr;

class Connection extends BaseConnection
{
    /**
     * The Elasticsearch client.
     *
     * @var \Elasticsearch\Client
     */
    protected $connection;

    protected $indexSuffix = '';

    protected $requestTimeout;

    /**
     * Create a new Elasticsearch connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->indexSuffix = $config['suffix'] ?? '';

        // Extract the hosts from config
        $hosts = explode(',', $config['hosts'] ?? $config['host']);

        // You can pass options directly to the client
        $options = Arr::get($config, 'options', []);

        // Create the connection
        $this->connection = $this->createConnection($hosts, $config, $options);

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection, $method], $parameters);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        //
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        //
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        //
    }

    /**
     * @param string $index
     * @param string $name
     */
    public function createAlias(string $index, string $name): void
    {
        $this->indices()->putAlias(compact('index', 'name'));
    }

    /**
     * @param string $index
     * @param array  $body
     */
    public function createIndex(string $index, array $body): void
    {
        $this->indices()->create(compact('index', 'body'));
    }

    /**
     * Run a select statement against the database and return a generator.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = false)
    {
        $scrollTimeout = '30s';
        $limit = $query['size'] ?? 0;

        $scrollParams = [
            'scroll' => $scrollTimeout,
            'size' => 100, // Number of results per shard
            'index' => $query['index'],
            'body' => $query['body'],
        ];

        $results = $this->select($scrollParams);

        $scrollId = $results['_scroll_id'];

        $numResults = count($results['hits']['hits']);

        foreach ($results['hits']['hits'] as $result) {
            yield $result;
        }

        if (!$limit || $limit > $numResults) {
            $limit = $limit ? $limit - $numResults : $limit;

            foreach ($this->scroll($scrollId, $scrollTimeout, $limit) as $result) {
                yield $result;
            }
        }
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function delete($query, $bindings = [])
    {
        return $this->run(
            $query,
            $bindings,
            Closure::fromCallable([$this->connection, 'delete'])
        );
    }

    /**
     * @param string $index
     */
    public function dropIndex(string $index): void
    {
        $this->indices()->delete(compact('index'));
    }

    /**
     * Get the timeout for the entire Elasticsearch request
     * @return float
     */
    public function getRequestTimeout(): float
    {
        return $this->requestTimeout;
    }

    /**
     * @return ElasticsearchBuilder|\Illuminate\Database\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new ElasticsearchBuilder($this);
    }

    /**
     * @return ElasticsearchGrammar|\Illuminate\Database\Schema\Grammars\Grammar
     */
    public function getSchemaGrammar()
    {
        return new ElasticsearchGrammar();
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->indexSuffix;
    }

    /**
     * Run an insert statement against the database.
     *
     * @param array $params
     * @param array $bindings
     *
     * @return bool
     */
    public function insert($params, $bindings = [])
    {
        return $this->run(
            $this->addClientParams($params),
            $bindings,
            Closure::fromCallable([$this->connection, 'bulk'])
        );
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param string     $query
     * @param array      $bindings
     * @param float|null $time
     *
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->event(new QueryExecuted(json_encode($query), $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * @return array
     */
    public function getQueryLogJson(): array
    {
        return array_map(
            function ($log) {
                return str_replace('    ', '  ', json_encode($log['query']['body'], JSON_PRETTY_PRINT));
            },
            $this->getQueryLog()
        );
    }

    /**
     * @return void
     */
    public function dumpQueryLogJson(): void
    {
        print '<pre>';

        foreach ($this->getQueryLogJson() as $query) {
            print $query . "\n\n";
        }

        print '</pre>';
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param array $bindings
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        return $bindings;
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     *
     * @return array
     */
    public function pretend(Closure $callback)
    {
        //
    }

    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     *
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {
        //
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack($toLevel = null)
    {
        //
    }

    /**
     * Run a select statement against the database using an Elasticsearch scroll cursor.
     *
     * @param string $scrollId
     * @param string $scrollTimeout
     * @param int    $limit
     *
     * @return \Generator
     */
    public function scroll(string $scrollId, string $scrollTimeout = '30s', int $limit = 0)
    {
        $numResults = 0;

        // Loop until the scroll 'cursors' are exhausted or we have enough results
        while (!$limit || $numResults < $limit) {
            // Execute a Scroll request
            $results = $this->connection->scroll([
                'body' => [
                    'scroll_id' => $scrollId,
                ],
                'scroll' => $scrollTimeout,
            ]);

            // Get new scroll ID in case it's changed
            $scrollId = $results['_scroll_id'];

            // Break if no results
            if (empty($results['hits']['hits'])) {
                break;
            }

            foreach ($results['hits']['hits'] as $result) {
                $numResults++;

                if ($limit && $numResults > $limit) {
                    break;
                }

                yield $result;
            }
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param array $params
     * @param array $bindings
     *
     * @return array
     */
    public function select($params, $bindings = [], $useReadPdo = true)
    {
        return $this->run(
            $this->addClientParams($params),
            $bindings,
            Closure::fromCallable([$this->connection, 'search'])
        );
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        //
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param string $prefix
     *
     * @return void
     */
    public function setIndexSuffix($suffix)
    {
        $this->indexSuffix = $suffix;

        $this->getQueryGrammar()->setIndexSuffix($suffix);
    }

    /**
     * Get the timeout for the entire Elasticsearch request
     *
     * @param float $requestTimeout seconds
     *
     * @return self
     */
    public function setRequestTimeout(float $requestTimeout): self
    {
        $this->requestTimeout = $requestTimeout;

        return $this;
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement($query, $bindings = [], Blueprint $blueprint = null)
    {
        //
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table, $as = null)
    {
        //
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param \Closure $callback
     * @param int      $attempts
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        //
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {
        //
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param string $query
     *
     * @return bool
     */
    public function unprepared($query)
    {
        //
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function update($query, $bindings = [])
    {
        return $this->run(
            $query,
            $bindings,
            Closure::fromCallable([$this->connection, 'index'])
        );
    }

    /**
     * @param string $index
     * @param array  $body
     */
    public function updateIndex(string $index, array $body): void
    {
        $this->indices()->putMapping(compact('index', 'body'));
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param \Illuminate\Database\Grammar $grammar
     *
     * @return \Illuminate\Database\Grammar
     */
    public function withIndexSuffix(BaseGrammar $grammar)
    {
        $grammar->setIndexSuffix($this->indexSuffix);

        return $grammar;
    }

    /**
     * Add client-specific parameters to the request params
     *
     * @param array $params
     *
     * @return array
     */
    protected function addClientParams(array $params): array
    {
        if ($this->requestTimeout) {
            $params['client']['timeout'] = $this->requestTimeout;
        }

        return $params;
    }

    /**
     * Create a new Elasticsearch connection.
     *
     * @param array $hosts
     * @param array $config
     *
     * @return \Elasticsearch\Client
     */
    protected function createConnection($hosts, array $config, array $options)
    {
        // apply config to each host
        $hosts = array_map(function ($host) use ($config) {
            $port = !empty($config['port']) ? $config['port'] : 9200;

            $scheme = !empty($config['scheme']) ? $config['scheme'] : 'http';

            // force https for port 443
            $scheme = (int) $port === 443 ? 'https' : $scheme;

            return [
                'host' => $host,
                'port' => $port,
                'scheme' => $scheme,
                'user' => !empty($config['username']) ? $config['username'] : null,
                'pass' => !empty($config['password']) ? $config['password'] : null,
            ];
        }, $hosts);

        return ClientBuilder::create()
            ->setHosts($hosts)
            ->setSelector('\Elasticsearch\ConnectionPool\Selectors\StickyRoundRobinSelector')
            ->build();
    }

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withIndexSuffix(new QueryGrammar);
    }

    /**
     * Run a search query.
     *
     * @param array    $query
     * @param array    $bindings
     * @param \Closure $callback
     *
     * @return mixed
     *
     * @throws \DesignMyNight\Elasticsearch\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        } catch (\Exception $e) {
            throw new QueryException($this->getName(), $query, $bindings, $e);
        }

        return $result;
    }
}
