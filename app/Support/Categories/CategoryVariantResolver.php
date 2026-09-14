<?php

namespace App\Support\Categories;

use App\Enums\CategoryType;
use App\Enums\CategoryVariant;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryVariantResolver
{
    public static function defaultForType(CategoryType $type): CategoryVariant
    {
        return $type === CategoryType::Expense
            ? CategoryVariant::Paced
            : CategoryVariant::Ordinary;
    }

    public static function fromRequest(CategoryType $type, Request $request, ?Category $existing = null): CategoryVariant
    {
        if ($existing?->isArchived()) {
            return $existing->category_variant;
        }

        $raw = $request->input('category_variant');

        if (! is_string($raw) || $raw === '') {
            if ($existing !== null) {
                return $existing->category_variant;
            }

            return self::defaultForType($type);
        }

        $variant = CategoryVariant::tryFrom($raw);

        if ($variant === null) {
            return self::defaultForType($type);
        }

        return self::coerceForType($type, $variant);
    }

    public static function coerceForType(CategoryType $type, CategoryVariant $variant): CategoryVariant
    {
        if ($type === CategoryType::Income) {
            return $variant === CategoryVariant::Agenda
                ? CategoryVariant::Agenda
                : CategoryVariant::Ordinary;
        }

        return match ($variant) {
            CategoryVariant::Paced, CategoryVariant::OneOff, CategoryVariant::Agenda => $variant,
            default => CategoryVariant::Paced,
        };
    }

    public static function isValidForType(CategoryType $type, CategoryVariant $variant): bool
    {
        if ($type === CategoryType::Income) {
            return in_array($variant, [CategoryVariant::Ordinary, CategoryVariant::Agenda], true);
        }

        return in_array($variant, [CategoryVariant::Paced, CategoryVariant::OneOff, CategoryVariant::Agenda], true);
    }
}
