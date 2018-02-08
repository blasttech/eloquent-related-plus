<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait SearchTrait
 *
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
     * @param array $searchFieldParameters
     * @param string $searchText
     * @return Builder
     */
    protected function checkSearchField($query, $table, $searchField, $searchFieldParameters, $searchText)
    {
        if (!isset($searchFieldParameters['regex']) || preg_match($searchFieldParameters['regex'], $searchText)) {
            $searchColumn = is_array($searchFieldParameters) ? $searchField : $searchFieldParameters;

            if (isset($searchFieldParameters['relation'])) {
                return $this->searchRelation($query, $searchFieldParameters, $searchColumn, $searchText);
            } else {
                return $this->searchThis($query, $searchFieldParameters, $table, $searchColumn, $searchText);
            }
        }

        return $query;
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
    protected function searchRelation(Builder $query, $searchFieldParameters, $searchColumn, $searchText)
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
    protected function searchThis(Builder $query, $searchFieldParameters, $table, $searchColumn, $searchText)
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
