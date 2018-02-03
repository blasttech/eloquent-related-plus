<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Interface RelatedPlus
 *
 * @package Blasttech\RelatedPlus
 */
interface RelatedPlus
{
    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     *
     * @param Builder $query
     * @param string $relation_name
     * @param string $operator
     * @param string $type
     * @param bool $where
     * @param bool $related_select
     * @param string|null $direction
     *
     * @return Builder
     */
    public function scopeModelJoin(
        Builder $query,
        $relation_name,
        $operator = '=',
        $type = 'left',
        $where = false,
        $related_select = true,
        $direction = null
    );

    /**
     * Join a model
     *
     * @param Builder $query
     * @param string $table_name
     * @param string $table_alias
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param string $operator
     * @param string $type
     * @param boolean $where
     * @param string|null $direction
     *
     * @return Builder
     */
    public function scopeRelationJoin(
        Builder $query,
        $table_name,
        $table_alias,
        $relation,
        $operator,
        $type,
        $where,
        $direction
    );

    /**
     * Set the order of a model
     *
     * @param Builder $query
     * @param string $order_field
     * @param string $dir
     * @return Builder
     */
    public function scopeOrderByCustom(Builder $query, $order_field, $dir);

    /**
     * Check if column being sorted by is from a related model
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCheckModel(Builder $query, $column, $direction);

    /**
     * Set the model order
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeSetCustomOrder(Builder $query, $column, $direction);

    /**
     * Switch a query to be a subquery of a model
     *
     * @param Builder $query
     * @param Builder $model
     * @return Builder
     */
    public function scopeSetSubquery(Builder $query, $model);

    /**
     * Use a model method to add columns or joins if in the order options
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    public function scopeOrderByWith(Builder $query, $order);

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param JoinClause $joinClause
     * @param string $column
     * @param \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param string $table
     * @param string $direction
     *
     * @return JoinClause
     */
    public function hasManyJoin(JoinClause $joinClause, $column, $relation, $table, $direction);

    /**
     * Add where statements for the model search fields
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, $search = '');
}