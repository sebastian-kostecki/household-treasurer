<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

    /**
     * Only an active Category is edited on the list; an archived one is restored first.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && ! $category->isArchived()
            && $this->user()?->can('update', $category) === true;
    }

    /**
     * Whether the type may change and whether the new key is free is decided by
     * `UpdateListCategory`, which also holds the unique constraint as the last defence.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->listCategoryRules();
    }
}
