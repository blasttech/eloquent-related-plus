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
     * Check relation type and get join
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @param \stdClass $table
     * @param string $operator
     * @param string|null $direction
     * @return Builder|JoinClause
     */
    protected function getRelationJoin($relation, $join, $table, $operator, $direction = null)
    {
        // If a HasOne relation and ordered - ie join to the latest/earliest
        if (class_basename($relation) === 'HasOne') {
            $relation = RelatedPlusHelpers::removeGlobalScopes($relation->getRelated(), $relation, 'order');

            if (!empty($relation->toBase()->orders)) {
                return $this->hasOneJoin($relation, $join);
            }
        }

        return $this->hasManyJoin($relation, $join, $table, $operator, $direction);
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

        return DB::raw('(' . RelatedPlusHelpers::toSqlWithBindings($subQuery) . ')');
    }

    /**
     * Adds a where for a relation's join columns and and min/max for a given column
     *
     * @param Builder $query
     * @param Relation $relation
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function joinOne($query, $relation, $column, $direction)
    {
        // Get join fields
        $joinColumns = $this->getJoinColumns($relation);

        return $this->selectMinMax(
            $query->whereColumn($joinColumns->first, '=', $joinColumns->second),
            $column,
            $direction
        );
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
            return $this->getBelongsToColumns($relation);
        }

        return $this->getHasOneOrManyColumns($relation);
    }

    /**
     * Get the join columns for a BelongsTo relation
     *
     * @param BelongsTo $relation
     * @return object
     */
    protected function getBelongsToColumns($relation)
    {
        $first = $relation->getOwnerKey();
        $second = $relation->getForeignKey();

        return (object)['first' => $first, 'second' => $second];
    }

    /**
     * Get the join columns for a HasOneOrMany relation
     *
     * @param HasOneOrMany $relation
     * @return object
     */
    protected function getHasOneOrManyColumns($relation)
    {
        $first = $relation->getQualifiedParentKeyName();
        $second = $relation->getQualifiedForeignKeyName();

        return (object)['first' => $first, 'second' => $second];
    }

    /**
     * Adds a select for a min or max on the given column, depending on direction given
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function selectMinMax($query, $column, $direction)
    {
        $sql_direction = ($direction == 'asc' ? 'MIN' : 'MAX');

        return $query->select(DB::raw($sql_direction . '(' . $this->addBackticks($column) . ')'));
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

    /**
     * Add wheres if they exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder|JoinClause $builder
     */
    protected function addRelatedWhereConstraints($builder, $relation, $table)
    {
        // Get where clauses from the relationship
        $wheres = collect($relation->toBase()->wheres)
            ->where('type', 'Basic')
            ->map(function ($where) use ($table) {
                // Add table name to column if it is absent
                return [
                    RelatedPlusHelpers::columnWithTableName($table, $where['column']),
                    $where['operator'],
                    $where['value']
                ];
            })->toArray();

        if (!empty($wheres)) {
            $builder->where($wheres);
        }

        return $builder;
    }

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param JoinClause $joinClause
     * @param string $column
     * @param HasMany|Relation $relation
     * @param string $table
     * @param string $direction
     *
     * @return JoinClause
     */
    protected function hasManyJoinWhere(JoinClause $joinClause, $column, $relation, $table, $direction)
    {
        return $joinClause->where(
            $column,
            function ($subQuery) use ($table, $direction, $relation, $column) {
                $subQuery = $this->joinOne(
                    $subQuery->from($table),
                    $relation,
                    $column,
                    $direction
                );

                // Add any where statements with the relationship
                $subQuery = $this->addRelatedWhereConstraints($subQuery, $relation, $table);

                // Add any order statements with the relationship
                return $this->addOrder($subQuery, $relation, $table);
            }
        );
    }

    /**
     * Add orderBy if orders exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder|JoinClause $builder
     */
    protected function addOrder($builder, $relation, $table)
    {
        if (!empty($relation->toBase()->orders)) {
            // Get where clauses from the relationship
            foreach ($relation->toBase()->orders as $order) {
                $builder->orderBy(RelatedPlusHelpers::columnWithTableName($table, $order['column']),
                    $order['direction']);
            }
        }

        return $builder;
    }
}
