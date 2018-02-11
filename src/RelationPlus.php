<?php

namespace Blasttech\EloquentRelatedPlus;

use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

/**
 * Class RelationPlus
 *
 * @package Blasttech\EloquentRelatedPlus\Relations
 */
class RelationPlus
{
    /**
     * @var string $tableName
     */
    public $tableName;

    /**
     * @var string $tableAlias
     */
    public $tableAlias;

    /**
     * @var BelongsTo|HasOneOrMany $relation
     */
    private $relation;

    public function __construct($relation)
    {
        $this->setRelation($relation);
        $this->tableName = $this->relation->getRelated()->getTable();
        $from = explode(' ', $this->relation->getQuery()->getQuery()->from);
        // if using a 'table' AS 'tableAlias' in a from statement, otherwise alias will be the table name
        $this->tableAlias = array_pop($from);
    }

    /**
     * @return BelongsTo|HasOneOrMany
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * @param BelongsTo|HasOneOrMany $relation
     */
    public function setRelation($relation)
    {
        $this->relation = $relation;
    }

    /**
     * Check relation type and get join
     *
     * @param JoinClause $join
     * @param string $operator
     * @param string|null $direction
     * @return Builder|JoinClause
     */
    public function getRelationJoin($join, $operator, $direction = null)
    {
        // If a HasOne relation and ordered - ie join to the latest/earliest
        if (class_basename($this->relation) === 'HasOne') {
            $relation = RelatedPlusHelpers::removeGlobalScopes($this->relation->getRelated(), $this->relation, 'order');

            if (!empty($relation->toBase()->orders)) {
                return $this->hasOneJoin($join);
            }
        }

        return $this->hasManyJoin($join, $operator, $direction);
    }

    /**
     * Join a HasOne relation which is ordered
     *
     * @param JoinClause $join
     * @return JoinClause
     */
    private function hasOneJoin($join)
    {
        // Get first relation order (should only be one)
        $order = $this->relation->toBase()->orders[0];

        return $join->on($order['column'], $this->hasOneJoinSql($order));
    }

    /**
     * Get join sql for a HasOne relation
     *
     * @param array $order
     * @return Expression
     */
    private function hasOneJoinSql($order)
    {
        // Build subquery for getting first/last record in related table
        $subQuery = $this
            ->joinOne(
                $this->relation->getRelated()->newQuery(),
                $order['column'],
                $order['direction']
            )
            ->setBindings($this->relation->getBindings());

        return DB::raw('(' . RelatedPlusHelpers::toSqlWithBindings($subQuery) . ')');
    }

    /**
     * Adds a where for a relation's join columns and and min/max for a given column
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    private function joinOne($query, $column, $direction)
    {
        // Get join fields
        $joinColumns = $this->getJoinColumns();

        return $this->selectMinMax(
            $query->whereColumn($joinColumns->first, '=', $joinColumns->second),
            $column,
            $direction
        );
    }

    /**
     * Get the join columns for a relation
     *
     * @return \stdClass
     */
    private function getJoinColumns()
    {
        // Get keys with table names
        if ($this->relation instanceof BelongsTo) {
            return $this->getBelongsToColumns();
        }

        return $this->getHasOneOrManyColumns();
    }

    /**
     * Get the join columns for a BelongsTo relation
     *
     * @return object
     */
    private function getBelongsToColumns()
    {
        $first = $this->relation->getOwnerKey();
        $second = $this->relation->getForeignKey();

        return (object)['first' => $first, 'second' => $second];
    }

    /**
     * Get the join columns for a HasOneOrMany relation
     *
     * @return object
     */
    private function getHasOneOrManyColumns()
    {
        $first = $this->relation->getQualifiedParentKeyName();
        $second = $this->relation->getQualifiedForeignKeyName();

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
    private function selectMinMax($query, $column, $direction)
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
    private function addBackticks($column)
    {
        return preg_match('/^[0-9a-zA-Z\.]*$/', $column) ?
            '`' . str_replace(['`', '.'], ['', '`.`'], $column) . '`' : $column;
    }

    /**
     * Join a HasMany Relation
     *
     * @param JoinClause $join
     * @param string $operator
     * @param string $direction
     * @return Builder|JoinClause
     */
    private function hasManyJoin($join, $operator, $direction)
    {
        // Get relation join columns
        $joinColumns = $this->getJoinColumns();
        $joinColumns = $this->replaceColumnTables($joinColumns);

        $join->on($joinColumns->first, $operator, $joinColumns->second);

        // Add any where clauses from the relationship
        $join = $this->addRelatedWhereConstraints($join); // $table->alias

        if (!is_null($direction) && get_class($this) === HasMany::class) {
            $join = $this->hasManyJoinWhere($join, $joinColumns->first, $direction); // $table->alias,
        }

        return $join;
    }

    /**
     * Replace column table names with aliases
     *
     * @param \stdClass $joinColumns
     * @return \stdClass
     */
    private function replaceColumnTables($joinColumns)
    {
        if ($this->tableName !== $this->tableAlias) {
            $joinColumns->first = str_replace($this->tableName, $this->tableAlias, $joinColumns->first);
            $joinColumns->second = str_replace($this->tableName, $this->tableAlias, $joinColumns->second);
        }

        return $joinColumns;
    }

    /**
     * Add wheres if they exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @return Builder|JoinClause $builder
     */
    private function addRelatedWhereConstraints($builder)
    {
        // Get where clauses from the relationship
        $wheres = collect($this->relation->toBase()->wheres)
            ->where('type', 'Basic')
            ->map(function ($where) {
                // Add table name to column if it is absent
                return [
                    $this->columnWithTableName($where['column']),
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
     * Add table name to column name if table name not already included in column name
     *
     * @param string $column
     * @return string
     */
    private function columnWithTableName($column)
    {
        return (preg_match('/(' . $this->tableAlias . '\.|`' . $this->tableAlias . '`)/i',
                $column) > 0 ? '' : $this->tableAlias . '.') . $column;
    }

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param JoinClause $joinClause
     * @param string $column
     * @param string $direction
     *
     * @return JoinClause
     */
    private function hasManyJoinWhere(JoinClause $joinClause, $column, $direction)
    {
        return $joinClause->where(
            $column,
            function ($subQuery) use ($direction, $column) {
                $subQuery = $this->joinOne(
                    $subQuery->from($this->tableAlias),
                    $column,
                    $direction
                );

                // Add any where statements with the relationship
                $subQuery = $this->addRelatedWhereConstraints($subQuery); // $this->tableAlias

                // Add any order statements with the relationship
                return $this->addOrder($subQuery); // $this->tableAlias
            }
        );
    }

    /**
     * Add orderBy if orders exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @return Builder|JoinClause $builder
     */
    private function addOrder($builder)
    {
        if (!empty($this->relation->toBase()->orders)) {
            // Get where clauses from the relationship
            foreach ($this->relation->toBase()->orders as $order) {
                $builder->orderBy($this->columnWithTableName($order['column']),
                    $order['direction']);
            }
        }

        return $builder;
    }

    /**
     * Get table name with alias if different to table name
     *
     * @return string
     */
    public function getTableWithAlias()
    {
        if ($this->tableAlias !== '' && $this->tableName !== $this->tableAlias) {
            return $this->tableName . ' AS ' . $this->tableAlias;
        }

        return $this->tableName;
    }
}