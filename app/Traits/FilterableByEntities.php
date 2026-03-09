<?php

namespace App\Traits;

trait FilterableByEntities
{
    /**
     * Apply an advanced AND/OR entity filter scope to the query using JSON operations.
     *
     * Format of $filters array:
     * [
     *   [10, 12], // AND group: Must have entity 10 AND 12
     *   [15, 18], // OR group: Must have entity 15 AND 18
     *   [20]      // OR group: Must have entity 20
     * ]
     *
     * If $filters is null or empty, no filtering is applied.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|null $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByEntities($query, ?array $filters)
    {
        if (empty($filters)) {
            return $query;
        }

        return $query->where(function ($q) use ($filters) {
            foreach ($filters as $andGroup) {
                // Ignore invalid or empty AND groups
                if (!is_array($andGroup) || empty($andGroup)) {
                    continue;
                }

                $q->orWhere(function ($subQ) use ($andGroup) {
                    // To satisfy an AND group, the user MUST have ALL entities
                    // in the array. Since we store data as {"TypeID": "EntityID"},
                    // and we only have EntityIDs in the array, we can use JSON_CONTAINS 
                    // dynamically on just the values.
                    
                    // JSON_CONTAINS(column, document) checks if the column contains the given JSON
                    // In Laravel, whereJsonContains allows checking an array of values where
                    // EACH must be present (doing it iteratively creates an AND chain)
                    foreach ($andGroup as $entityId) {
                         // We don't precisely know the TypeID key here, but MySQL's 
                         // JSON_CONTAINS can search for the existence of the value
                         // anywhere within the object if formatted correctly.
                         // However, checking just the values is tricky in SQL if the keys are dynamic.
                         
                         // The safest way to do "Contains this value regardless of key" natively
                         // JSON_SEARCH returns the path to the value if it exists, or NULL.
                         $subQ->whereRaw("JSON_SEARCH(taxonomy_properties, 'one', ?) IS NOT NULL", [(string) $entityId]);
                    }
                });
            }
        });
    }
}
