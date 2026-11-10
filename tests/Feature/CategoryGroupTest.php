<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category Groups in „Kategorie” (ADR-0063, ADR-0069): create, rename, delete,
 * "Grupa…" and the order of the list.
 */
class CategoryGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->post(route('category-groups.store'), ['type' => 'expense', 'name' => 'Zdrowie'])
            ->assertRedirect(route('login'));
    }

    public function test_a_group_is_created_at_the_end_of_the_types_sequence(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->expense()->create(['sort_order' => 0]);
        Category::factory()->for($user)->expense()->create(['sort_order' => 1]);
        Category::factory()->for($user)->income()->create(['sort_order' => 7]);

        $this->actingAs($user)
            ->post(route('category-groups.store'), ['type' => 'expense', 'name' => '  Zdrowie '])
            ->assertRedirect(route('categories.index'));

        $group = CategoryGroup::query()->where('user_id', $user->id)->sole();
        $this->assertSame('Zdrowie', $group->name);
        $this->assertSame('zdrowie', $group->name_key);
        $this->assertSame('expense', $group->type->value);
        $this->assertSame(2, $group->sort_order);
    }

    public function test_a_duplicate_name_in_the_same_type_is_a_name_error_but_the_other_type_may_reuse_it(): void
    {
        $user = User::factory()->create();
        CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 0]);

        $this->actingAs($user)
            ->post(route('category-groups.store'), ['type' => 'expense', 'name' => ' ZDROWIE'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, CategoryGroup::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->post(route('category-groups.store'), ['type' => 'income', 'name' => 'Zdrowie'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, CategoryGroup::query()->where('user_id', $user->id)->count());
    }

    public function test_a_group_can_be_renamed_but_not_to_the_name_of_another_group_of_the_type(): void
    {
        $user = User::factory()->create();
        $health = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 0]);
        CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Opłaty', 'sort_order' => 1]);

        $this->actingAs($user)
            ->patch(route('category-groups.update', $health), ['name' => 'opłaty'])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->patch(route('category-groups.update', $health), ['name' => 'ZDROWIE'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertSame('ZDROWIE', $health->fresh()->name);
        $this->assertSame('zdrowie', $health->fresh()->name_key);
    }

    public function test_another_users_group_cannot_be_renamed_or_deleted(): void
    {
        $user = User::factory()->create();
        $foreign = CategoryGroup::factory()->for(User::factory())->expense()->create(['name' => 'Cudza']);

        $this->actingAs($user)
            ->patch(route('category-groups.update', $foreign), ['name' => 'Moja'])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('category-groups.destroy', $foreign))
            ->assertForbidden();

        $this->assertSame('Cudza', $foreign->fresh()->name);
    }

    public function test_deleting_a_group_leaves_its_categories_in_place_without_a_group(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 1]);
        $first = Category::factory()->for($user)->expense()->create(['name' => 'Żywność', 'sort_order' => 0]);
        $pharmacy = Category::factory()->for($user)->expense()->create(['category_group_id' => $group->id, 'name' => 'Apteka', 'sort_order' => 2]);
        $tests = Category::factory()->for($user)->expense()->create(['category_group_id' => $group->id, 'name' => 'Badania', 'sort_order' => 3]);
        $last = Category::factory()->for($user)->expense()->create(['name' => 'Rozrywka', 'sort_order' => 4]);

        $this->actingAs($user)
            ->delete(route('category-groups.destroy', $group))
            ->assertRedirect(route('categories.index'));

        $this->assertModelMissing($group);
        $this->assertSame([0, null], [$first->fresh()->sort_order, $first->fresh()->category_group_id]);
        $this->assertSame([1, null], [$pharmacy->fresh()->sort_order, $pharmacy->fresh()->category_group_id]);
        $this->assertSame([2, null], [$tests->fresh()->sort_order, $tests->fresh()->category_group_id]);
        $this->assertSame([3, null], [$last->fresh()->sort_order, $last->fresh()->category_group_id]);
    }

    public function test_a_category_goes_to_the_end_of_the_chosen_group_or_out_of_it(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $member = Category::factory()->for($user)->expense()->create(['category_group_id' => $group->id, 'name' => 'Apteka', 'sort_order' => 1]);
        $loose = Category::factory()->for($user)->expense()->create(['name' => 'Rozrywka', 'sort_order' => 2]);

        $this->actingAs($user)
            ->patch(route('categories.group', $loose), ['category_group_id' => $group->id])
            ->assertRedirect(route('categories.index'));

        $this->assertSame([$group->id, 2], [$loose->fresh()->category_group_id, $loose->fresh()->sort_order]);
        $this->assertSame([$group->id, 1], [$member->fresh()->category_group_id, $member->fresh()->sort_order]);

        $this->actingAs($user)
            ->patch(route('categories.group', $member), ['category_group_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertSame([null, 2], [$member->fresh()->category_group_id, $member->fresh()->sort_order]);
        $this->assertSame([$group->id, 1], [$loose->fresh()->category_group_id, $loose->fresh()->sort_order]);
    }

    public function test_a_group_of_another_type_is_refused(): void
    {
        $user = User::factory()->create();
        $incomeGroup = CategoryGroup::factory()->for($user)->income()->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->actingAs($user)
            ->patch(route('categories.group', $category), ['category_group_id' => $incomeGroup->id])
            ->assertSessionHasErrors('category_group_id');

        $this->assertNull($category->fresh()->category_group_id);
    }

    public function test_another_users_group_and_category_are_refused(): void
    {
        $user = User::factory()->create();
        $foreignGroup = CategoryGroup::factory()->for(User::factory())->expense()->create();
        $foreignCategory = Category::factory()->for(User::factory())->expense()->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->actingAs($user)
            ->patch(route('categories.group', $category), ['category_group_id' => $foreignGroup->id])
            ->assertSessionHasErrors('category_group_id');
        $this->actingAs($user)
            ->patch(route('categories.group', $foreignCategory), ['category_group_id' => null])
            ->assertForbidden();

        $this->assertNull($category->fresh()->category_group_id);
    }

    public function test_an_archived_category_cannot_be_moved_to_a_group(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create();
        $archived = Category::factory()->for($user)->expense()->archived()->create();

        $this->actingAs($user)
            ->patch(route('categories.group', $archived), ['category_group_id' => $group->id])
            ->assertSessionHasErrors('category_group_id');

        $this->assertNull($archived->fresh()->category_group_id);
    }

    public function test_the_tree_is_saved_with_membership_and_order(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $inside = Category::factory()->for($user)->expense()->create(['category_group_id' => $group->id, 'name' => 'Apteka', 'sort_order' => 1]);
        $outside = Category::factory()->for($user)->expense()->create(['name' => 'Rozrywka', 'sort_order' => 2]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder-list'), [
                'type' => 'expense',
                'items' => [
                    ['kind' => 'category', 'id' => $inside->id],
                    ['kind' => 'group', 'id' => $group->id, 'category_ids' => [$outside->id]],
                ],
            ])
            ->assertNoContent();

        $this->assertSame([null, 0], [$inside->fresh()->category_group_id, $inside->fresh()->sort_order]);
        $this->assertSame(1, $group->fresh()->sort_order);
        $this->assertSame([$group->id, 2], [$outside->fresh()->category_group_id, $outside->fresh()->sort_order]);
    }

    public function test_an_archived_category_keeps_its_place_when_the_tree_is_saved(): void
    {
        $user = User::factory()->create();
        $first = Category::factory()->for($user)->expense()->create(['name' => 'A', 'sort_order' => 0]);
        $archived = Category::factory()->for($user)->expense()->archived()->create(['name' => 'B', 'sort_order' => 1]);
        $last = Category::factory()->for($user)->expense()->create(['name' => 'C', 'sort_order' => 2]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder-list'), [
                'type' => 'expense',
                'items' => [
                    ['kind' => 'category', 'id' => $last->id],
                    ['kind' => 'category', 'id' => $first->id],
                ],
            ])
            ->assertNoContent();

        $this->assertSame(0, $last->fresh()->sort_order);
        $this->assertSame(1, $archived->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_a_tree_with_an_incomplete_set_is_refused(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $first = Category::factory()->for($user)->expense()->create(['sort_order' => 1]);
        Category::factory()->for($user)->expense()->create(['sort_order' => 2]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder-list'), [
                'type' => 'expense',
                'items' => [
                    ['kind' => 'category', 'id' => $first->id],
                    ['kind' => 'group', 'id' => $group->id, 'category_ids' => []],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $group->fresh()->sort_order);
    }

    public function test_a_tree_with_a_foreign_or_other_type_category_is_refused(): void
    {
        $user = User::factory()->create();
        $mine = Category::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $income = Category::factory()->for($user)->income()->create();
        $foreign = Category::factory()->for(User::factory())->expense()->create();

        foreach ([$income, $foreign] as $stranger) {
            $this->actingAs($user)
                ->patchJson(route('categories.reorder-list'), [
                    'type' => 'expense',
                    'items' => [
                        ['kind' => 'category', 'id' => $mine->id],
                        ['kind' => 'category', 'id' => $stranger->id],
                    ],
                ])
                ->assertUnprocessable();
        }
    }

    public function test_a_category_created_on_the_list_goes_after_the_groups_of_its_type(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->expense()->create(['sort_order' => 0]);
        CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Opłaty stałe', 'sort_order' => 1]);

        $this->actingAs($user)
            ->post(route('categories.list.store'), ['name' => 'Wakacje', 'icon' => 'tag', 'color' => '#868e96', 'type' => 'expense', 'rhythm' => 'running'])
            ->assertSessionHasNoErrors();

        $created = Category::query()->where('user_id', $user->id)->where('name', 'Wakacje')->sole();
        $this->assertSame(2, $created->sort_order);
        $this->assertNull($created->category_group_id);
    }

    public function test_a_type_change_on_the_list_takes_the_category_off_its_group_to_the_end_of_the_new_type(): void
    {
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $member = Category::factory()->for($user)->expense()->create(['category_group_id' => $group->id, 'sort_order' => 1]);
        Category::factory()->for($user)->income()->create(['sort_order' => 0]);
        CategoryGroup::factory()->for($user)->income()->create(['name' => 'Pensje', 'sort_order' => 1]);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $member), ['name' => $member->name, 'icon' => 'tag', 'color' => '#868e96', 'type' => 'income'])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('income', $member->type->value);
        $this->assertNull($member->category_group_id);
        $this->assertSame(2, $member->sort_order);
    }
}
