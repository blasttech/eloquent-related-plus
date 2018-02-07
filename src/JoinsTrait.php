<?php

namespace Blasttech\EloquentRelatedPlus;

use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

/**
 * Trait JoinsTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait JoinsTrait
{
    /**
     * Check relation type and join
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @param \stdClass $table
     * @param string $operator
     * @param string $direction
     * @return Builder|JoinClause
     */
    protected function relationJoinType($relation, $join, $table, $operator, $direction = null)
    {
        // If a HasOne relation and ordered - ie join to the latest/earliest
        if (class_basename($relation) === 'HasOne' && !empty($relation->toBase()->orders)) {
            return $this->hasOneJoin($relation, $join);
        } else {
            return $this->hasManyJoin($relation, $join, $table, $operator, $direction);
        }
    }

    /**
     * Join a HasOne relation which is ordered
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @return JoinClause
     */
    protected function hasOneJoin($relation, $join)
    {
        // Get first relation order (should only be one)
        $order = $relation->toBase()->orders[0];

        return $join->on($order['column'], $this->hasOneJoinSql($relation, $order));
    }

    /**
     * Get join sql for a HasOne relation
     *
     * @param Relation $relation
     * @param array $order
     * @return Expression
     */
    protected function hasOneJoinSql($relation, $order)
    {
        // Build subquery for getting first/last record in related table
        $subQuery = $this
            ->joinOne(
                $relation->getRelated()->newQuery(),
                $relation,
                $order['column'],
                $order['direction']
            )
            ->setBindings($relation->getBindings());

        return DB::raw('(' . $this->toSqlWithBindings($subQuery) . ')');
    }

    /**
     * Join a HasMany Relation
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @param \stdClass $table
     * @param string $operator
     * @param string $direction
     * @return Builder|JoinClause
     */
    protected function hasManyJoin($relation, $join, $table, $operator, $direction)
    {
        // Get relation join columns
        $joinColumns = $this->getJoinColumns($relation);
        $joinColumns = $this->replaceColumnTables($joinColumns, $table);

        $join->on($joinColumns->first, $operator, $joinColumns->second);

        // Add any where clauses from the relationship
        $join = $this->addRelatedWhereConstraints($join, $relation, $table->alias);

        if (!is_null($direction) && get_class($relation) === HasMany::class) {
            $join = $this->hasManyJoinWhere($join, $joinColumns->first, $relation, $table->alias, $direction);
        }

        return $join;
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
     * Replace column table names with aliases
     *
     * @param \stdClass $joinColumns
     * @param \stdClass $table
     * @return \stdClass
     */
    protected function replaceColumnTables($joinColumns, $table)
    {
        if ($table->name !== $table->alias) {
            $joinColumns->first = str_replace($table->name, $table->alias, $joinColumns->first);
            $joinColumns->second = str_replace($table->name, $table->alias, $joinColumns->second);
        }

        return $joinColumns;
    }
}
