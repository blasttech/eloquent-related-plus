<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait SearchTrait
 *
 * @property array attributes
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait SearchTrait
{
    /**
     * Add where statements for search fields to search for searchText
     *
     * @param Builder $query
     * @param string $searchText
     * @return Builder
     */
    protected function checkSearchFields($query, $searchText)
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
     * @param array $fieldParameters
     * @param string $searchText
     * @return Builder
     */
    protected function checkSearchField($query, $table, $searchField, $fieldParameters, $searchText)
    {
        if (!isset($fieldParameters['regex']) || preg_match($fieldParameters['regex'], $searchText)) {
            $searchColumn = is_array($fieldParameters) ? $searchField : $fieldParameters;

            if (isset($fieldParameters['relation'])) {
                return $this->searchRelation($query, $fieldParameters, $searchColumn, $searchText);
            }

            return $this->searchThis($query, $fieldParameters, $table, $searchColumn, $searchText);
        }

        return $query;
    }

    /**
     * Add where condition to search a relation
     *
     * @param Builder $query
     * @param array $fieldParameters
     * @param string $searchColumn
     * @param string $searchText
     * @return Builder
     */
    protected function searchRelation(Builder $query, $fieldParameters, $searchColumn, $searchText)
    {
        $relation = $fieldParameters['relation'];
        $relatedTable = $this->$relation()->getRelated()->getTable();

        return $query->orWhere(function (Builder $query) use (
            $searchText,
            $searchColumn,
            $relation,
            $relatedTable
        ) {
            return $query->orWhereHas($relation, function (Builder $query2) use (
                $searchText,
                $searchColumn,
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
     * @param array $fieldParameters
     * @param string $table
     * @param string $searchColumn
     * @param string $searchText
     * @return Builder
     */
    protected function searchThis(Builder $query, $fieldParameters, $table, $searchColumn, $searchText)
    {
        $searchOperator = $fieldParameters['operator'] ?? 'like';
        $searchValue = $fieldParameters['value'] ?? '%{{search}}%';

        return $query->orWhere(
            $table . '.' . $searchColumn,
            $searchOperator,
            str_replace('{{search}}', $searchText, $searchValue)
        );
    }
}
