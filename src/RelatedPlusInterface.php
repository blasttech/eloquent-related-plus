<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface RelatedPlus
 *
 * @package Blasttech\RelatedPlus
 */
interface RelatedPlusInterface
{
    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
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
    );

    /**
     * Join a model
     *
     * @param Builder $query
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
     * @param string $orderField
     * @param string $dir
     * @return Builder
     */
    public function scopeOrderByCustom(Builder $query, $orderField, $dir);

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
     * Add where statements for the model search fields
     *
     * @param Builder $query
     * @param string $searchText
     * @return Builder
     */
    public function scopeSearch(Builder $query, $searchText = '');
}
