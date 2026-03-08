<?php

namespace App\Traits;

trait FilterableByEntities
{
    /**
     * Apply an advanced AND/OR entity filter scope to the query.
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
                    // in the array. We enforce this by checking that the count 
                    // of matching entities equals the size of the array.
                    $subQ->whereHas('entities', function ($entityQ) use ($andGroup) {
                        $entityQ->whereIn('tenant_entities.id', $andGroup);
                    }, '=', count($andGroup));
                });
            }
        });
    }
}
