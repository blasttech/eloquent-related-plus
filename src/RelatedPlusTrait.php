<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Trait RelatedPlusTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 *
 * @package Blasttech\WherePlus
 */
trait RelatedPlusTrait
{
    use HelperMethodTrait;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    abstract function getTable();

    /**
     * Boot method for trait
     *
     */
    public static function bootRelatedPlusTrait()
    {
        static::saving(function ($model) {
            if (!empty($model->nullable)) {
                foreach ($model->attributes as $key => $value) {
                    if (isset($model->nullable[$key])) {
                        $model->{$key} = empty(trim($value)) ? null : $value;
                    }
                }
            }
        });
    }

    /**
     * Add joins for one or more relations
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     * Usages:
     * $query->modelJoin('customers')
     * $query->modelJoin('customer.client')
     *
     * @param Builder $query
     * @param string $relationName
     * @param string $operator
     * @param string $type
     * @param bool $where
     * @param bool $relatedSelect
     * @param string|null $direction
     *
     * @return Builder
     */
    public function scopeModelJoin(
        Builder $query,
        $relationName,
        $operator = '=',
        $type = 'left',
        $where = false,
        $relatedSelect = true,
        $direction = null
    ) {
        $connection = $this->connection;

        foreach ($this->parseRelationNames($relationName) as $relation) {
            $tableName = $relation->getRelated()->getTable();
            // if using a 'table' AS 'tableAlias' in a from statement, otherwise alias will be the table name
            $from = explode(' ', $relation->getQuery()->getQuery()->from);
            $tableAlias = array_pop($from);

            if (empty($query->getQuery()->columns)) {
                $query->select($this->getTable() . ".*");
            }
            if ($relatedSelect) {
                foreach (Schema::connection($connection)->getColumnListing($tableName) as $relatedColumn) {
                    $query->addSelect(
                        new Expression("`$tableAlias`.`$relatedColumn` AS `$tableAlias.$relatedColumn`")
                    );
                }
            }
            $query->relationJoin($tableName, $tableAlias, $relation, $operator, $type, $where, $direction);
        }

        return $query;
    }

    /**
     * Join a model
     *
     * @param Builder $query
     * @param string $tableName
     * @param string $tableAlias
     * @param Relation $relation
     * @param string $operator
     * @param string $type
     * @param boolean $where
     * @param null $direction
     * @return Builder
     */
    public function scopeRelationJoin(
        Builder $query,
        $tableName,
        $tableAlias,
        $relation,
        $operator,
        $type,
        $where,
        $direction = null
    ) {
        if ($tableAlias !== '' && $tableName !== $tableAlias) {
            $fullTableName = $tableName . ' AS ' . $tableAlias;
        } else {
            $fullTableName = $tableName;
        }

        return $query->join($fullTableName, function (JoinClause $join) use (
            $tableName,
            $tableAlias,
            $relation,
            $operator,
            $direction
        ) {
            // If a HasOne relation and ordered - ie join to the latest/earliest
            if (class_basename($relation) === 'HasOne' && !empty($relation->toBase()->orders)) {
                return $this->hasOneJoin($relation, $join);
            } else {
                return $this->hasManyJoin($relation, $join, $tableName, $tableAlias, $operator, $direction);
            }
        }, null, null, $type, $where);
    }

    /**
     * Join a HasOne relation which is ordered
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @return JoinClause
     */
    private function hasOneJoin($relation, $join)
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
    public function hasOneJoinSql($relation, $order)
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
     * Adds a where for a relation's join columns and and min/max for a given column
     *
     * @param Builder $query
     * @param Relation $relation
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function joinOne($query, $relation, $column, $direction)
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
            $first = $relation->getOwnerKey();
            $second = $relation->getForeignKey();
        } else {
            $first = $relation->getQualifiedParentKeyName();
            $second = $relation->getQualifiedForeignKeyName();
        }

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
    public function selectMinMax($query, $column, $direction)
    {
        $column = $this->addBackticks($column);

        /** @var Model $query */
        if ($direction == 'asc') {
            return $query->select(DB::raw('MIN(' . $column . ')'));
        } else {
            return $query->select(DB::raw('MAX(' . $column . ')'));
        }
    }

    /**
     * Join a HasMany Relation
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @param string $tableName
     * @param string $tableAlias
     * @param string $operator
     * @param string $direction
     * @return Builder|JoinClause
     */
    private function hasManyJoin($relation, $join, $tableName, $tableAlias, $operator, $direction)
    {
        // Get relation join columns
        $joinColumns = $this->getJoinColumns($relation);

        $first = $joinColumns->first;
        $second = $joinColumns->second;
        if ($tableName !== $tableAlias) {
            $first = str_replace($tableName, $tableAlias, $first);
            $second = str_replace($tableName, $tableAlias, $second);
        }

        $join->on($first, $operator, $second);

        // Add any where clauses from the relationship
        $join = $this->addWhereConstraints($join, $relation, $tableAlias);

        if (!is_null($direction) && get_class($relation) === HasMany::class) {
            $join = $this->hasManyJoinWhere($join, $first, $relation, $tableAlias, $direction);
        }

        return $join;
    }

    /**
     * Add wheres if they exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder|JoinClause
     */
    protected function addWhereConstraints($builder, $relation, $table)
    {
        // Get where clauses from the relationship
        $wheres = collect($relation->toBase()->wheres)
            ->where('type', 'Basic')
            ->map(function ($where) use ($table) {
                // Add table name to column if it is absent
                return [$this->columnWithTableName($table, $where['column']), $where['operator'], $where['value']];
            })->toArray();

        if (!empty($wheres)) {
            $builder->where($wheres);
        }

        return $builder;
    }

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param Builder|JoinClause $joinClause
     * @param string $column
     * @param HasMany|Relation $relation
     * @param string $table
     * @param string $direction
     *
     * @return JoinClause
     */
    public function hasManyJoinWhere(JoinClause $joinClause, $column, $relation, $table, $direction)
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
                $subQuery = $this->addWhereConstraints($subQuery, $relation, $table);

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
     * @return Builder
     */
    protected function addOrder($builder, $relation, $table)
    {
        if (!empty($relation->toBase()->orders)) {
            // Get where clauses from the relationship
            foreach ($relation->toBase()->orders as $order) {
                $builder->orderBy($this->columnWithTableName($table, $order['column']), $order['direction']);
            }
        }

        return $builder;
    }

    /**
     * Set the order of a model
     *
     * @param Builder $query
     * @param string $orderField
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCustom(Builder $query, $orderField, $direction)
    {
        if ($this->hasFieldsAndDefaults($orderField, $direction)) {
            $query = $this->removeGlobalScope($query, 'order');
        }

        return $query->setCustomOrder($orderField, $direction);
    }

    /**
     * Check $order_fields and $order_defaults are set
     *
     * @param $orderField
     * @param $direction
     * @return bool
     */
    private function hasFieldsAndDefaults($orderField, $direction)
    {
        if (!isset($this->order_fields) || !is_array($this->order_fields)) {
            throw new InvalidArgumentException(get_class($this) . ' order fields not set correctly.');
        } else {
            if (($orderField === '' || $direction === '')
                && (!isset($this->order_defaults) || !is_array($this->order_defaults))) {
                throw new InvalidArgumentException(get_class($this) . ' order defaults not set and not overriden.');
            } else {
                return true;
            }
        }
    }

    /**
     * Check if column being sorted by is from a related model
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCheckModel(Builder $query, $column, $direction)
    {
        /** @var Model $query */
        $query->orderBy(DB::raw($column), $direction);

        $periodPos = strpos($column, '.');
        if (isset($this->order_relations) && ($periodPos !== false || isset($this->order_relations[$column]))) {
            $table = ($periodPos !== false ? substr($column, 0, $periodPos) : $column);
            $query = $this->joinRelatedTable($query, $table);
        }

        return $query;
    }

    /**
     * Join a related table if not already joined
     *
     * @param Builder $query
     * @param string $table
     * @return Builder
     */
    private function joinRelatedTable($query, $table)
    {
        if (isset($this->order_relations[$table]) &&
            !$this->hasJoin($query, $table, $this->order_relations[$table])) {
            $columnRelations = $this->order_relations[$table];

            $query->modelJoin(
                $columnRelations,
                '=',
                'left',
                false,
                false
            );
        }

        return $query;
    }

    /**
     * Set the model order
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeSetCustomOrder(Builder $query, $column, $direction)
    {
        if (isset($this->order_defaults)) {
            $column = $this->setColumn($column);
            $direction = $this->setDirection($direction);
        }

        return $this->setOrder($query, $column, $direction);
    }

    /**
     * Override column if provided column not valid
     *
     * @param $column
     * @return string
     */
    private function setColumn($column)
    {
        // If $column not in order_fields list, use default
        if ($column == '' || !isset($this->order_fields[$column])) {
            $column = $this->order_defaults['field'];
        }

        return $column;
    }

    /**
     * Override direction if provided direction not valid
     *
     * @param string $direction
     * @return string
     */
    private function setDirection($direction)
    {
        // If $direction not asc or desc, use default
        if ($direction == '' || !in_array(strtoupper($direction), ['ASC', 'DESC'])) {
            $direction = $this->order_defaults['dir'];
        }

        return $direction;
    }

    /**
     * Set order based on order_fields
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    private function setOrder($query, $column, $direction)
    {
        if (!is_array($this->order_fields[$column])) {
            $query->orderByCheckModel($this->order_fields[$column], $direction);
        } else {
            foreach ($this->order_fields[$column] as $dbField) {
                $query->orderByCheckModel($dbField, $direction);
            }
        }

        return $query;
    }

    /**
     * Switch a query to be a subquery of a model
     *
     * @param Builder $query
     * @param Builder $model
     * @return Builder
     */
    public function scopeSetSubquery(Builder $query, $model)
    {
        $sql = $this->toSqlWithBindings($model);
        $table = $model->getQuery()->from;

        return $query
            ->from(DB::raw("({$sql}) as " . $table))
            ->select($table . '.*');
    }

    /**
     * Use a model method to add columns or joins if in the order options
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    public function scopeOrderByWith(Builder $query, $order)
    {
        if (isset($this->order_with[$order])) {
            $with = 'with' . $this->order_with[$order];

            $query->$with();
        }

        if (isset($this->order_fields[$order])) {
            $orderOption = (explode('.', $this->order_fields[$order]))[0];

            if (isset($this->order_relations[$orderOption])) {
                $query->modelJoin(
                    $this->order_relations[$orderOption],
                    '=',
                    'left',
                    false,
                    false
                );
            }
        }

        return $query;
    }

    /**
     * Add where statements for the model search fields
     *
     * @param Builder $query
     * @param string $searchText
     * @return Builder
     */
    public function scopeSearch(Builder $query, $searchText = '')
    {
        $searchText = trim($searchText);

        // If search is set
        if ($searchText != "") {
            if (!isset($this->search_fields) || !is_array($this->search_fields) || empty($this->search_fields)) {
                throw new InvalidArgumentException(get_class($this) . ' search properties not set correctly.');
            } else {
                $query = $this->checkSearchFields($query, $searchText);
            }
        }

        return $query;
    }

    /**
     * Add where statements for search fields to search for searchText
     *
     * @param Builder $query
     * @param string $searchText
     * @return Builder
     */
    private function checkSearchFields($query, $searchText)
    {
        return $query->where(function (Builder $query) use ($searchText) {
            if (isset($this->search_fields) && !empty($this->search_fields)) {
                /** @var Model $this */
                $table = $this->getTable();
                foreach ($this->search_fields as $searchField => $searchFieldParameters) {
                    $query = $this->checkSearchField($query, $table, $searchField, $searchFieldParameters, $searchText);
                }
            }

            return $query;
        });
    }

    /**
     * Add where statement for a search field
     *
     * @param Builder $query
     * @param string $table
     * @param string $searchField
     * @param array $searchFieldParameters
     * @param string $searchText
     * @return Builder
     */
    private function checkSearchField($query, $table, $searchField, $searchFieldParameters, $searchText)
    {
        if (!isset($searchFieldParameters['regex']) || preg_match($searchFieldParameters['regex'], $searchText)) {
            $searchColumn = is_array($searchFieldParameters) ? $searchField : $searchFieldParameters;

            if (isset($searchFieldParameters['relation'])) {
                return $this->searchRelation($query, $searchFieldParameters, $searchColumn, $searchText);
            } else {
                return $this->searchThis($query, $searchFieldParameters, $table, $searchColumn, $searchText);
            }
        } else {
            return $query;
        }
    }

    /**
     * Add where condition to search a relation
     *
     * @param Builder $query
     * @param array $searchFieldParameters
     * @param string $searchColumn
     * @param string $searchText
     * @return Builder
     */
    private function searchRelation(Builder $query, $searchFieldParameters, $searchColumn, $searchText)
    {
        $relation = $searchFieldParameters['relation'];
        $relatedTable = $this->$relation()->getRelated()->getTable();

        return $query->orWhere(function (Builder $query) use (
            $searchText,
            $searchColumn,
            $searchFieldParameters,
            $relation,
            $relatedTable
        ) {
            return $query->orWhereHas($relation, function (Builder $query2) use (
                $searchText,
                $searchColumn,
                $searchFieldParameters,
                $relatedTable
            ) {
                return $query2->where($relatedTable . '.' . $searchColumn, 'like', $searchText . '%');
            });
        });
    }

    /**
     * Add where condition to search current model
     *
     * @param Builder $query
     * @param array $searchFieldParameters
     * @param string $table
     * @param string $searchColumn
     * @param string $searchText
     * @return Builder
     */
    public function searchThis(Builder $query, $searchFieldParameters, $table, $searchColumn, $searchText)
    {
        $searchOperator = $searchFieldParameters['operator'] ?? 'like';
        $searchValue = $searchFieldParameters['value'] ?? '%{{search}}%';

        return $query->orWhere(
            $table . '.' . $searchColumn,
            $searchOperator,
            str_replace('{{search}}', $searchText, $searchValue)
        );
    }
}
