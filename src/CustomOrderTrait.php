<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait RelatedPlusTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait CustomOrderTrait
{
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
     * @param string $column
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
}