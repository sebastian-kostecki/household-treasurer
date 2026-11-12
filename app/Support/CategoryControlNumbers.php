<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `categories` section of the control numbers (ADR-0085): query builder only, like
 * `ControlNumbers`, so the reading also works on a schema that is older than the code.
 */
final class CategoryControlNumbers
{
    /**
     * Every list Category by id: type, name key, archive flag, group and number of Rhythm
     * entries. Null on the old shape, where the list does not exist yet.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function collect(string $lineTable): ?array
    {
        if ($lineTable !== 'budget_lines' || ! Schema::hasTable('categories')) {
            return null;
        }

        $rhythms = Schema::hasTable('category_rhythms')
            ? DB::table('category_rhythms')->groupBy('category_id')->selectRaw('category_id, count(*) as entries')->pluck('entries', 'category_id')
            : collect();
        $hasGroup = Schema::hasColumn('categories', 'category_group_id');
        $categories = [];

        foreach (DB::table('categories')->orderBy('id')->get() as $row) {
            $categories[(int) $row->id] = [
                'type' => $row->type,
                'name_key' => $row->name_key,
                'archived' => $row->archived_at !== null,
                'group' => $hasGroup && $row->category_group_id !== null ? (int) $row->category_group_id : null,
                'rhythm_entries' => (int) ($rhythms[$row->id] ?? 0),
            ];
        }

        return $categories;
    }
}
