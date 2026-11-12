<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\CategoryList\CategoryMergeGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * One validation for the preview (GET with `target_id` in the query) and for
 * the merge (POST), ADR-0078. The target of another User fails the `exists`
 * rule scoped to the User, so the response never reveals that it exists.
 */
class MergeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && $this->user()?->can('merge', $category) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $source = $this->route('category');

                if ($validator->errors()->isNotEmpty() || ! $source instanceof Category) {
                    return;
                }

                try {
                    CategoryMergeGuard::assertMergeable($source, $this->targetCategory());
                } catch (ValidationException $exception) {
                    $validator->errors()->add('target_id', $exception->errors()['target_id'][0]);
                }
            },
        ];
    }

    public function targetCategory(): Category
    {
        return Category::query()
            ->where('user_id', $this->user()?->id)
            ->findOrFail($this->integer('target_id'));
    }
}
