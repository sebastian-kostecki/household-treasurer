<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCategoryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && $this->user()?->can('update', $category) === true;
    }

    /**
     * The group must belong to the User; its type is checked against the
     * Category by the action.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_group_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('category_groups', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
