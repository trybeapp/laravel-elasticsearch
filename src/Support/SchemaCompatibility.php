<?php

namespace DesignMyNight\Elasticsearch\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Grammar as BaseGrammar;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Laravel 12 changed Illuminate\Database\Grammar and
 * Illuminate\Database\Schema\Blueprint to take the connection as their first
 * constructor argument. On Laravel 10 and 11 neither accepts one.
 *
 * This package supports all three, so the expected shape is detected from the
 * framework itself rather than pinned to a version number.
 */
class SchemaCompatibility
{
    /** @var bool|null */
    private static $grammarExpectsConnection;

    /** @var bool|null */
    private static $blueprintExpectsConnection;

    /**
     * Does the base grammar require a connection to construct?
     *
     * @return bool
     */
    public static function grammarExpectsConnection(): bool
    {
        if (self::$grammarExpectsConnection === null) {
            self::$grammarExpectsConnection = self::expectsConnection(BaseGrammar::class);
        }

        return self::$grammarExpectsConnection;
    }

    /**
     * Does the base blueprint require a connection to construct?
     *
     * @return bool
     */
    public static function blueprintExpectsConnection(): bool
    {
        if (self::$blueprintExpectsConnection === null) {
            self::$blueprintExpectsConnection = self::expectsConnection(BaseBlueprint::class);
        }

        return self::$blueprintExpectsConnection;
    }

    /**
     * Determine whether the given class takes a connection as its first
     * constructor argument.
     *
     * @param string $class
     *
     * @return bool
     */
    private static function expectsConnection(string $class): bool
    {
        if (!method_exists($class, '__construct')) {
            return false;
        }

        $parameters = (new ReflectionMethod($class, '__construct'))->getParameters();

        if ($parameters === []) {
            return false;
        }

        $type = $parameters[0]->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), Connection::class, true);
    }
}
