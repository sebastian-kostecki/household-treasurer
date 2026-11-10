<?php

use App\Support\CategoryList\CategoryListOrder;

/*
 * The order of a type's list (ADR-0066): one sequence shared by groups and
 * Categories. Ids: groups 1xx, Categories 1-99. The state of the plan's example:
 * Kredyt 0, Opłaty stałe (group 101) 1, Czynsz 2, Prąd 3, Wakacje 4,
 * Zdrowie (group 102) 5, Apteka 6.
 */

function orderGroup(int $id, int $sortOrder, ?string $nameKey = null): array
{
    return ['id' => $id, 'sort_order' => $sortOrder, 'name_key' => $nameKey ?? 'group-'.$id];
}

function orderCategory(int $id, ?int $groupId, int $sortOrder, ?string $nameKey = null): array
{
    return ['id' => $id, 'group_id' => $groupId, 'sort_order' => $sortOrder, 'name_key' => $nameKey ?? 'category-'.$id];
}

/**
 * Kredyt 1, Opłaty stałe 101 (Czynsz 2, Prąd 3), Wakacje 4, Zdrowie 102 (Apteka 5).
 *
 * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
 */
function orderExample(): array
{
    return [
        [orderGroup(101, 1), orderGroup(102, 5)],
        [
            orderCategory(1, null, 0),
            orderCategory(2, 101, 2),
            orderCategory(3, 101, 3),
            orderCategory(4, null, 4),
            orderCategory(5, 102, 6),
        ],
    ];
}

/**
 * @param  array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}  $result
 * @return list<string>
 */
function orderSequence(array $result): array
{
    $places = [];
    foreach ($result['groups'] as $id => $place) {
        $places[$place] = 'g'.$id;
    }
    foreach ($result['categories'] as $id => $category) {
        $places[$category['sort_order']] = 'c'.$id.($category['group_id'] === null ? '' : '@'.$category['group_id']);
    }
    ksort($places);

    return array_values($places);
}

function assertOrderIsContiguous(array $result): void
{
    $places = [...array_values($result['groups']), ...array_column($result['categories'], 'sort_order')];
    sort($places);
    expect($places)->toBe(range(0, count($places) - 1));

    foreach ($result['categories'] as $category) {
        if ($category['group_id'] !== null) {
            expect($result['groups'][$category['group_id']])->toBeLessThan($category['sort_order']);
        }
    }
}

test('the tree puts the categories of a group after it and numbers the sequence 0 to n-1', function () {
    [$groups, $categories] = orderExample();

    $result = CategoryListOrder::normalize($groups, $categories);

    expect(orderSequence($result))->toBe(['c1', 'g101', 'c2@101', 'c3@101', 'c4', 'g102', 'c5@102']);
    assertOrderIsContiguous($result);
});

test('moving a group takes its hidden category along', function () {
    [$groups, $categories] = orderExample();
    // Prąd (3) is hidden, as in a Budget where it has no active line.
    $items = [
        ['kind' => 'group', 'id' => 102, 'category_ids' => [5]],
        ['kind' => 'category', 'id' => 1],
        ['kind' => 'group', 'id' => 101, 'category_ids' => [2]],
        ['kind' => 'category', 'id' => 4],
    ];

    $result = CategoryListOrder::reorder($groups, $categories, $items, [1, 2, 4, 5], [101, 102]);

    expect(orderSequence($result))->toBe(['g102', 'c5@102', 'c1', 'g101', 'c3@101', 'c2@101', 'c4']);
    assertOrderIsContiguous($result);
});

test('a category dropped into another group changes its group and its place', function () {
    [$groups, $categories] = orderExample();
    $items = [
        ['kind' => 'category', 'id' => 1],
        ['kind' => 'group', 'id' => 101, 'category_ids' => [3]],
        ['kind' => 'group', 'id' => 102, 'category_ids' => [2, 5]],
        ['kind' => 'category', 'id' => 4],
    ];

    $result = CategoryListOrder::reorder($groups, $categories, $items, [1, 2, 3, 4, 5], [101, 102]);

    expect(orderSequence($result))->toBe(['c1', 'g101', 'c3@101', 'g102', 'c2@102', 'c5@102', 'c4']);
    assertOrderIsContiguous($result);
});

test('a category dragged out of its group stands without a group', function () {
    [$groups, $categories] = orderExample();
    $items = [
        ['kind' => 'category', 'id' => 1],
        ['kind' => 'group', 'id' => 101, 'category_ids' => [3]],
        ['kind' => 'category', 'id' => 2],
        ['kind' => 'category', 'id' => 4],
        ['kind' => 'group', 'id' => 102, 'category_ids' => [5]],
    ];

    $result = CategoryListOrder::reorder($groups, $categories, $items, [1, 2, 3, 4, 5], [101, 102]);

    expect($result['categories'][2]['group_id'])->toBeNull()
        ->and(orderSequence($result))->toBe(['c1', 'g101', 'c3@101', 'c2', 'c4', 'g102', 'c5@102']);
    assertOrderIsContiguous($result);
});

test('a subset keeps the hidden categories of other years in their places', function () {
    // Budget view: only Czynsz (2), Wakacje (4) and group 101 are visible; Kredyt (1),
    // Prąd (3), Apteka (5) and group 102 are hidden.
    [$groups, $categories] = orderExample();
    $items = [
        ['kind' => 'category', 'id' => 4],
        ['kind' => 'group', 'id' => 101, 'category_ids' => [2]],
    ];

    $result = CategoryListOrder::reorder($groups, $categories, $items, [2, 4], [101]);

    // Visible places were 1 (g101), 2 (c2), 4 (c4); refilled with c4, g101, c2.
    expect(orderSequence($result))->toBe(['c1', 'c4', 'g101', 'c3@101', 'c2@101', 'g102', 'c5@102']);
    assertOrderIsContiguous($result);
});

test('a tree that is not exactly the visible set is refused', function () {
    [$groups, $categories] = orderExample();

    expect(fn () => CategoryListOrder::reorder($groups, $categories, [['kind' => 'category', 'id' => 1]], [1, 4], []))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => CategoryListOrder::reorder($groups, $categories, [['kind' => 'category', 'id' => 1], ['kind' => 'category', 'id' => 1]], [1, 1], []))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => CategoryListOrder::reorder($groups, $categories, [['kind' => 'category', 'id' => 99]], [99], []))
        ->toThrow(InvalidArgumentException::class);
});

test('assigning puts the category at the end of the group or, without a group, at the end of the sequence', function () {
    [$groups, $categories] = orderExample();

    $toGroup = CategoryListOrder::assign($groups, $categories, 4, 101);
    $out = CategoryListOrder::assign($groups, $categories, 2, null);

    expect(orderSequence($toGroup))->toBe(['c1', 'g101', 'c2@101', 'c3@101', 'c4@101', 'g102', 'c5@102'])
        ->and(orderSequence($out))->toBe(['c1', 'g101', 'c3@101', 'c4', 'g102', 'c5@102', 'c2']);
    assertOrderIsContiguous($toGroup);
    assertOrderIsContiguous($out);
});

test('assigning to an unknown category or group is refused', function () {
    [$groups, $categories] = orderExample();

    expect(fn () => CategoryListOrder::assign($groups, $categories, 99, null))->toThrow(InvalidArgumentException::class);
    expect(fn () => CategoryListOrder::assign($groups, $categories, 1, 999))->toThrow(InvalidArgumentException::class);
});

test('deleting a group in the middle leaves its categories in place without a group', function () {
    [$groups, $categories] = orderExample();

    $result = CategoryListOrder::deleteGroup($groups, $categories, 101);

    expect(orderSequence($result))->toBe(['c1', 'c2', 'c3', 'c4', 'g102', 'c5@102'])
        ->and($result['groups'])->not->toHaveKey(101)
        ->and($result['categories'][2]['group_id'])->toBeNull();
    assertOrderIsContiguous($result);
    expect(fn () => CategoryListOrder::deleteGroup($groups, $categories, 999))->toThrow(InvalidArgumentException::class);
});

test('the tree is rebuilt from membership and sort order when the sequence is inconsistent', function () {
    // Gaps, a category of a group below its group, a category with an unknown group.
    $groups = [orderGroup(101, 10), orderGroup(102, 40)];
    $categories = [
        orderCategory(1, 101, 3),
        orderCategory(2, 101, 50),
        orderCategory(3, 777, 20),
        orderCategory(4, 102, 7),
        orderCategory(5, null, 99),
    ];

    $result = CategoryListOrder::normalize($groups, $categories);

    expect(orderSequence($result))->toBe(['g101', 'c1@101', 'c2@101', 'c3', 'g102', 'c4@102', 'c5']);
    assertOrderIsContiguous($result);
});

test('a tie of a group and a category puts the group first, ties of two categories go by name key then id', function () {
    $groups = [orderGroup(101, 2, 'zzz')];
    $categories = [
        orderCategory(3, null, 2, 'aaa'),
        orderCategory(1, null, 1, 'beta'),
        orderCategory(2, null, 1, 'alfa'),
        orderCategory(5, null, 1, 'alfa'),
    ];

    $result = CategoryListOrder::normalize($groups, $categories);

    expect(orderSequence($result))->toBe(['c2', 'c5', 'c1', 'g101', 'c3']);
});

test('the same ties decide the order inside a group', function () {
    $groups = [orderGroup(101, 0)];
    $categories = [
        orderCategory(2, 101, 5, 'b'),
        orderCategory(1, 101, 5, 'b'),
        orderCategory(3, 101, 5, 'a'),
    ];

    expect(orderSequence(CategoryListOrder::normalize($groups, $categories)))->toBe(['g101', 'c3@101', 'c1@101', 'c2@101']);
});

test('the next place is after the highest place of groups and categories', function () {
    expect(CategoryListOrder::nextSortOrder([], []))->toBe(0)
        ->and(CategoryListOrder::nextSortOrder([['sort_order' => 7]], [['sort_order' => 3]]))->toBe(8)
        ->and(CategoryListOrder::nextSortOrder([['sort_order' => 2]], [['sort_order' => 9]]))->toBe(10);
});
