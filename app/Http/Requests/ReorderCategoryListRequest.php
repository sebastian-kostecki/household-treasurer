<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * `items` is the visible tree: groups with the ids of their Categories and
     * Categories without a group, in order.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CategoryType::class)],
            'items' => ['present', 'array'],
            'items.*.kind' => ['required', Rule::in(['group', 'category'])],
            'items.*.id' => ['required', 'integer'],
            'items.*.category_ids' => ['present_if:items.*.kind,group', 'array'],
            'items.*.category_ids.*' => ['required', 'integer'],
        ];
    }
}
