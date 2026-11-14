<?php

namespace App\Enums;

enum CategoriesListTab: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Archive = 'archive';
}
