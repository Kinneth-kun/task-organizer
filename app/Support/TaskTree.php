<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Hierarchical numbering (1, 1.2, 1.2.3) and depth-first ordering of task trees.
 *
 * Numbers come from the full tree, so everyone sees the same number for the same task even when
 * department walls hide some of its siblings.
 */
class TaskTree
{
    /** @return array<int, string> task id => "1.2" for every live task in the project */
    public static function numbersForProject(int $projectId): array
    {
        return self::number(
            Task::query()->where('project_id', $projectId)->orderBy('position')->orderBy('id')->get(['id', 'parent_id', 'position'])
        );
    }

    /** Number for one task: project-wide for project tasks, relative to the root for standalone trees. */
    public static function numberFor(Task $task): ?string
    {
        if ($task->project_id) {
            return self::numbersForProject($task->project_id)[$task->id] ?? null;
        }

        if (! $task->parent_id) {
            return null;
        }

        $root = $task;
        $guard = 0;
        while ($root->parent_id && $guard++ < 10) {
            $root = Task::query()->find($root->parent_id) ?? $root;
        }

        return self::subtreeNumbers($root)[$task->id] ?? null;
    }

    /** @return array<int, string> numbers for a standalone tree, its root unnumbered */
    public static function subtreeNumbers(Task $root): array
    {
        $tasks = collect([$root]);
        $frontier = [$root->id];
        while ($frontier) {
            $children = Task::query()->whereIn('parent_id', $frontier)->orderBy('position')->orderBy('id')->get(['id', 'parent_id', 'position']);
            $tasks = $tasks->concat($children);
            $frontier = $children->pluck('id')->all();
        }

        $numbers = self::number($tasks, $root->id);
        unset($numbers[$root->id]);

        return $numbers;
    }

    /**
     * Depth-first order for display. Tasks whose parent is not in the collection are treated as
     * roots. Each item gets `tree_depth` set.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    public static function flatten(Collection $tasks): Collection
    {
        $ids = $tasks->pluck('id')->flip();
        $byParent = $tasks->groupBy(fn (Task $t) => $t->parent_id && isset($ids[$t->parent_id]) ? $t->parent_id : 0);

        $ordered = collect();
        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $ordered) {
            foreach (($byParent[$parentId] ?? collect())->sortBy([['position', 'asc'], ['id', 'asc']]) as $task) {
                $task->setAttribute('tree_depth', $depth);
                $ordered->push($task);
                $walk($task->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $ordered;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, string>
     */
    private static function number(Collection $tasks, ?int $rootId = null): array
    {
        $byParent = $tasks->groupBy(fn ($t) => $t->parent_id ?? 0);
        $numbers = [];

        $walk = function (int $parentId, string $prefix) use (&$walk, $byParent, &$numbers) {
            $i = 0;
            foreach ($byParent[$parentId] ?? [] as $task) {
                $number = $prefix === '' ? (string) ++$i : $prefix.'.'.++$i;
                $numbers[$task->id] = $number;
                $walk($task->id, $number);
            }
        };

        if ($rootId) {
            $numbers[$rootId] = '';
            $walk($rootId, '');
        } else {
            $walk(0, '');
        }

        return $numbers;
    }
}
