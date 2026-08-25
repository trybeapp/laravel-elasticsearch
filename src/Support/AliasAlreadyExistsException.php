<?php

namespace DesignMyNight\Elasticsearch\Support;

use Exception;

/**
 * Thrown when an alias cannot be created because the name is already taken by
 * a different index.
 *
 * Distinct from a generic failure on purpose: callers that only want the alias
 * to exist (migrate:mappings re-runs, a rebuilt environment) can treat this as
 * a warning, while callers that meant to point it somewhere new still see a
 * real conflict. An alias that already points at the requested index is not
 * this — that is a no-op, see MappingService::createAlias().
 */
class AliasAlreadyExistsException extends Exception
{
    /** @var string */
    private $alias;

    /** @var string[] */
    private $indices;

    /**
     * @param string   $alias   Name of the alias that is already taken.
     * @param string[] $indices Indices the alias currently points at.
     */
    public function __construct(string $alias, array $indices = [])
    {
        $this->alias = $alias;
        $this->indices = $indices;

        $message = "Alias {$alias} already exists";

        if ($indices !== []) {
            $message .= ' on ' . implode(', ', $indices);
        }

        parent::__construct($message);
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return string[]
     */
    public function getIndices(): array
    {
        return $this->indices;
    }
}
