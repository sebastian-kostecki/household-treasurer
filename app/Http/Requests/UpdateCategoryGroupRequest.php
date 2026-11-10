<?php

namespace App\Http\Requests;

use App\Models\CategoryGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('categoryGroup');

        return $group instanceof CategoryGroup && $this->user()?->can('update', $group) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
