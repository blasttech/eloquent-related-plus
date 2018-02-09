<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Class RelatedPlusHelpers
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
class RelatedPlusHelpers
{
    /**
     * Get relation table name and alias
     *
     * @param Relation $relation
     * @return \stdClass
     */
    public static function getRelationTables($relation)
    {
        $table = new \stdClass();
        $table->name = $relation->getRelated()->getTable();
        // if using a 'table' AS 'tableAlias' in a from statement, otherwise alias will be the table name
        $from = explode(' ', $relation->getQuery()->getQuery()->from);
        $table->alias = array_pop($from);

        return $table;
    }

    /**
     * Get the relations from a relation name
     * $relationName can be a single relation
     * Usage for User model:
     * parseRelationNames('customer') returns [$user->customer()]
     * parseRelationNames('customer.contact') returns [$user->customer(), $user->customer->contact()]
     *
     * @param Model $model
     * @param string $relationName
     * @return Relation[]
     */
    public static function parseRelationNames($model, $relationName)
    {
        $relationNames = explode('.', $relationName);
        $parentRelationName = null;
        $relations = [];

        foreach ($relationNames as $relationName) {
            if (is_null($parentRelationName)) {
                $relations[] = $model->$relationName();
                $parentRelationName = $model->$relationName()->getRelated();
            } else {
                $relations[] = $parentRelationName->$relationName();
            }
        }

        return $relations;
    }

    /**
     * Return the sql for a query with the bindings replaced with the binding values
     *
     * @param Builder $builder
     * @return string
     */
    public static function toSqlWithBindings($builder)
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
    public static function columnWithTableName($table, $column)
    {
        return (preg_match('/(' . $table . '\.|`' . $table . '`)/i', $column) > 0 ? '' : $table . '.') . $column;
    }

    /**
     * Get table name from column name
     *
     * @param string $column
     * @return string
     */
    public static function getTableFromColumn($column)
    {
        $periodPos = strpos($column, '.');

        return ($periodPos !== false ? substr($column, 0, $periodPos) : $column);
    }

    /**
     * Get table name with alias if different to table name
     *
     * @param \stdClass $table
     * @return string
     */
    public static function getTableWithAlias($table)
    {
        if ($table->alias !== '' && $table->name !== $table->alias) {
            return $table->name . ' AS ' . $table->alias;
        }

        return $table->name;
    }

    /**
     * Remove a global scope if it exists
     *
     * @param Model $model
     * @param Builder $query
     * @param string $scopeName
     * @return Builder
     */
    public static function removeGlobalScope($model, $query, $scopeName)
    {
        $globalScopes = $model->getGlobalScopes();
        if (isset($globalScopes[$scopeName])) {
            $query->withoutGlobalScope($scopeName);
        }

        return $query;
    }
}
