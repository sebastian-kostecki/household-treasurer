<?php

use App\Support\SheetLoad\SheetMapping;

/*
 * The mapping of the old budget sheet to the Category list, accepted by the user (legacy-budget-import, ADR-0085).
 * Labels are normalised: lower case, no trailing `*`, "work:" written as "praca:". A target is a Category id,
 * SheetMapping::SKIP (a transfer, not an operation) or SheetMapping::NEW_CATEGORY (the archived "Kosmetyki").
 * Ids 1-38 come from the production copy on dev; `categories` holds the name and type each id must have in the
 * target database (the command refuses on any difference).
 */
return [
    'expenses' => [
        'rata kredytu' => 9,
        'czynsz' => 11,
        'telefony' => 14,
        'internet' => 14,
        'abonamenty' => 23,
        'prąd' => 15,
        'przedszkole' => 18,
        'żłobek' => 18,
        'żłobek/przedszkole' => 18,
        'ubezpieczenie' => 19,
        'codzienne wydatki' => 8,
        'środki czystości' => 8,
        'jedzenie poza domem' => 24,
        'kosmetyki' => SheetMapping::NEW_CATEGORY,
        'kosmetyczka' => SheetMapping::NEW_CATEGORY,
        'fryzjer' => SheetMapping::NEW_CATEGORY,
        'kosmetyczka i fryzjer' => SheetMapping::NEW_CATEGORY,
        'apteka' => 12,
        'badania' => 20,
        'dentysta - ortodoncja' => 20,
        'leczenie prywatne' => 20,
        'bilety - parking - inne' => 22,
        'ubezpieczenie auta' => 21,
        'fundusz remontowy' => 17,
        'paliwo' => 16,
        'rozrywka i wyjścia' => 23,
        'wyjazdy i wycieczki' => 25,
        'amortyzacja' => 10,
        'fundusz wydatków nieregularnych' => 10,
        'oszczędności długoterminowe' => SheetMapping::SKIP,
    ],

    // 2022: the breakdown of "Codzienne wydatki" in G25:I31.
    'daily_breakdown' => [
        'jedzenie' => 8,
        'słodycze i inne' => 8,
        'środki czystości' => 8,
        'pomoce kuchenne' => 8,
        'higiena i kosmetyki' => 29,
        'pieluchy' => 32,
    ],

    // The "kategoria" column (J) of the irregular list; an empty cell and "-" are the key ''.
    'irregular_categories' => [
        'ubrania' => 27,
        'osobiste' => 29,
        'kosmetyki' => SheetMapping::NEW_CATEGORY,
        'usługi' => 33,
        'prezenty' => 31,
        'edukacja' => 30,
        'darowizny' => 28,
        'książki' => 34,
        'dziecko' => 32,
        'elektronika' => 36,
        'eksploatacja' => 35,
        'dom' => 37,
        'wyposażenie' => 37,
        'wyposażanie' => 37,
        'meble' => 37,
        'praca' => 10,
        '' => 10,
    ],

    // The difference between a block total and its list: one "reszta bez rozpisania" operation in this Category.
    'remainders' => [
        'irregular' => 10,
        'medical' => 20,
        'daily' => 8,
    ],

    'income' => [
        'praca: sebastian' => 1,
        'praca: jana' => 2,
        '500+' => 3,
        'darowizny' => 4,
        'odsetki' => 5,
        'handel' => 39,
        'ukraina' => 7,
        'przychody nieregularne' => 7,
        'nieregularne' => 7,
        'pozostałe' => 7,
        'inne' => 7,
        'oszczędności' => SheetMapping::SKIP,
    ],

    // Part of a cell becomes a separate operation in another Category.
    'overrides' => [
        ['year' => 2023, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 683_107, 'category' => 6, 'description' => 'Zwrot podatku'],
        ['year' => 2024, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 582_320, 'category' => 6, 'description' => 'Zwrot podatku'],
        ['year' => 2025, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 579_012, 'category' => 6, 'description' => 'Zwrot podatku'],
    ],

    'categories' => [
        1 => ['name' => 'Wynagrodzenie Sebastian', 'type' => 'income'],
        2 => ['name' => 'Wynagrodzenie Janeczka', 'type' => 'income'],
        3 => ['name' => '800+', 'type' => 'income'],
        4 => ['name' => 'Darowizny', 'type' => 'income'],
        5 => ['name' => 'Odsetki', 'type' => 'income'],
        6 => ['name' => 'Zwrot podatku', 'type' => 'income'],
        7 => ['name' => 'Pozostałe', 'type' => 'income'],
        8 => ['name' => 'Artykuły spożywcze', 'type' => 'expense'],
        9 => ['name' => 'Kredyt', 'type' => 'expense'],
        10 => ['name' => 'Pozostałe', 'type' => 'expense'],
        11 => ['name' => 'Czynsz', 'type' => 'expense'],
        12 => ['name' => 'Apteka', 'type' => 'expense'],
        14 => ['name' => 'Telefon + Internet', 'type' => 'expense'],
        15 => ['name' => 'Prąd', 'type' => 'expense'],
        16 => ['name' => 'Paliwo', 'type' => 'expense'],
        17 => ['name' => 'Serwis samochodu', 'type' => 'expense'],
        18 => ['name' => 'Przedszkole', 'type' => 'expense'],
        19 => ['name' => 'Ubezpieczenia', 'type' => 'expense'],
        20 => ['name' => 'Leczenie prywatne', 'type' => 'expense'],
        21 => ['name' => 'Ubezpieczenie samochodu', 'type' => 'expense'],
        22 => ['name' => 'Bilety i parking', 'type' => 'expense'],
        23 => ['name' => 'Rozrywka', 'type' => 'expense'],
        24 => ['name' => 'Restaruacje', 'type' => 'expense'],
        25 => ['name' => 'Wakacje', 'type' => 'expense'],
        27 => ['name' => 'Ubrania', 'type' => 'expense'],
        28 => ['name' => 'Darowizny', 'type' => 'expense'],
        29 => ['name' => 'Rzeczy osobiste', 'type' => 'expense'],
        30 => ['name' => 'Edukacja', 'type' => 'expense'],
        31 => ['name' => 'Prezenty', 'type' => 'expense'],
        32 => ['name' => 'Dzieci', 'type' => 'expense'],
        33 => ['name' => 'Usługi', 'type' => 'expense'],
        34 => ['name' => 'Książki', 'type' => 'expense'],
        35 => ['name' => 'Eksploatacja', 'type' => 'expense'],
        36 => ['name' => 'Elektronika', 'type' => 'expense'],
        37 => ['name' => 'Dom i wyposażenie', 'type' => 'expense'],
        39 => ['name' => 'Handel', 'type' => 'income'],
    ],

    // Created archived, without joining the drafts, with one Running entry of the Rhythm from 2022-01.
    'new_category' => [
        'name' => 'Kosmetyki',
        'type' => 'expense',
        'icon' => 'sparkles',
        'color' => '#ec4899',
        'rhythm_start' => '2022-01',
    ],
];
