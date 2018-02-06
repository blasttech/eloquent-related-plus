<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait HelperMethodTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait HelperMethodTrait
{

    /**
     * Get relation table name and alias
     *
     * @param Relation $relation
     * @return \stdClass
     */
    protected function getRelationTables($relation)
    {
        $table = new \stdClass();
        $table->name = $relation->getRelated()->getTable();
        // if using a 'table' AS 'tableAlias' in a from statement, otherwise alias will be the table name
        $from = explode(' ', $relation->getQuery()->getQuery()->from);
        $table->alias = array_pop($from);

        return $table;
    }

    /**
     * Get the join columns for a relation
     *
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @return \stdClass
     */
    protected function getJoinColumns($relation)
    {
        // Get keys with table names
        if ($relation instanceof BelongsTo) {
            $first = $relation->getOwnerKey();
            $second = $relation->getForeignKey();
        } else {
            $first = $relation->getQualifiedParentKeyName();
            $second = $relation->getQualifiedForeignKeyName();
        }

        return (object)['first' => $first, 'second' => $second];
    }

    /**
     * Get the relations from a relation name
     * $relationName can be a single relation
     * Usage for User model:
     * parseRelationNames('customer') returns [$user->customer()]
     * parseRelationNames('customer.contact') returns [$user->customer(), $user->customer->contact()]
     *
     * @param string $relationName
     * @return Relation[]
     */
    protected function parseRelationNames($relationName)
    {
        $relationNames = explode('.', $relationName);
        $parentRelationName = null;
        $relations = [];

        foreach ($relationNames as $relationName) {
            if (is_null($parentRelationName)) {
                $relations[] = $this->$relationName();
                $parentRelationName = $this->$relationName()->getRelated();
            } else {
                $relations[] = $parentRelationName->$relationName();
            }
        }

        return $relations;
    }

    /**
     * Add backticks to a table/column
     *
     * @param string $column
     * @return string
     */
    protected function addBackticks($column)
    {
        return preg_match('/^[0-9a-zA-Z\.]*$/', $column) ?
            '`' . str_replace(['`', '.'], ['', '`.`'], $column) . '`' : $column;
    }

    /**
     * Return the sql for a query with the bindings replaced with the binding values
     *
     * @param Builder $builder
     * @return string
     */
    protected function toSqlWithBindings(Builder $builder)
    {
        $replacements = array_map('addslashes', $builder->getBindings());
        $sql = $builder->toSql();

        foreach ($replacements as &$replacement) {
            if (!is_numeric($replacement)) {
                $replacement = '"' . $replacement . '"';
            }
        }

        return preg_replace_callback(
            '/(\?)(?=(?:[^\'"]|["\'][^\'"]*["\'])*$)/',
            function () use (&$replacements) {
                return array_shift($replacements);
            },
            $sql
        );
    }

    /**
     * Add table name to column name if table name not already included in column name
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    protected function columnWithTableName($table, $column)
    {
        return (preg_match('/(' . $table . '\.|`' . $table . '`)/i', $column) > 0 ? '' : $table . '.') . $column;
    }

    /**
     * Remove a global scope if it exists
     *
     * @param Builder $query
     * @param string $scopeName
     * @return Builder
     */
    protected function removeGlobalScope($query, $scopeName)
    {
        /** @var Model $this */
        $globalScopes = $this->getGlobalScopes();
        if (isset($globalScopes[$scopeName])) {
            $query->withoutGlobalScope($scopeName);
        }

        return $query;
    }

    /**
     * Check if relation exists in eager loads
     *
     * @param Builder $builder
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @return bool
     */
    protected function isEagerLoaded(Builder $builder, $relation)
    {
        $eagerLoads = $builder->getEagerLoads();

        return !is_null($eagerLoads) && in_array($relation, $eagerLoads);
    }
}
