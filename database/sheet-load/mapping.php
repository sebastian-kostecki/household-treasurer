<?php

use App\Support\SheetLoad\SheetMapping;

/*
 * The mapping of the old budget sheet to the Category list, accepted by the user (legacy-budget-import, ADR-0085).
 * Labels are normalised: lower case, no trailing `*`, "work:" written as "praca:". A target is a Category id,
 * SheetMapping::SKIP (a transfer, not an operation, outside "Analiza"), SheetMapping::FUND (a fund movement of 2020-2021,
 * ADR-0096: no operation, but "Analiza" counts it; only in `expenses` and `income`) or SheetMapping::NEW_CATEGORY
 * (the archived "Kosmetyki"; no label uses it since "Kosmetyki" became Category 42 "Chemia i kosmetyki").
 * Ids come from production; `categories` holds the name and type each id must have in the target database (the
 * command refuses on any difference), as the list stands after it was rebuilt on 2026-11-13 (ADR-0096, decision 6).
 */
return [
    'expenses' => [
        'rata kredytu' => 9,
        'czynsz' => 11,
        'telefony' => 14,
        'internet' => 44,
        'abonamenty' => 23,
        'prąd' => 15,
        'przedszkole' => 18,
        'żłobek' => 18,
        'żłobek/przedszkole' => 18,
        'ubezpieczenie' => 19,
        'codzienne wydatki' => 8,
        'środki czystości' => 42,
        'jedzenie poza domem' => 24,
        'kosmetyki' => 42,
        'kosmetyczka' => 42,
        'fryzjer' => 42,
        'kosmetyczka i fryzjer' => 42,
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

        // 2020-2021 (ADR-0096).
        'hbo go' => 45,
        'netflix' => 45,
        'ogrzewanie' => 35,
        'bilety i transport' => 22,
        'lekarz - dentysta' => 20,
        'lekarz (dentysta)' => 20,
        'fundusz wydatków celowych' => SheetMapping::FUND,
        'fundusz wydatków celowych (oszcz.)' => SheetMapping::FUND,
        'rezerwa na wydatki' => SheetMapping::FUND,
        'święta' => SheetMapping::FUND,
        'prezenty' => SheetMapping::FUND,
        'nadpłata kredytu' => SheetMapping::FUND,
    ],

    // 2022: the breakdown of "Codzienne wydatki" in G25:I31; 2020-2021 add the two labels below.
    'daily_breakdown' => [
        'gospodarstwo domowe' => 8,
        'jedzenie poza domem' => 24,
        'jedzenie' => 8,
        'słodycze i inne' => 8,
        'środki czystości' => 42,
        'pomoce kuchenne' => 8,
        'higiena i kosmetyki' => 42,
        'pieluchy' => 32,
    ],

    // The "kategoria" column (J) of the irregular list; an empty cell and "-" are the key ''.
    'irregular_categories' => [
        'ubrania' => 27,
        'osobiste' => 29,
        'kosmetyki' => 42,
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

        // 2020-2021 (ADR-0096).
        'lekarz' => 20,
        'badania' => 20,
        'gospodarstwo' => 37,
        'jedzenie' => 24,
        'darowizna' => 28,
        'prezent' => 31,
        'ubranie' => 27,
        'biurowe' => 30,
        'szkoła' => 30,
        'higiena' => 29,
        'kary' => 10,
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

        // 2020-2021 (ADR-0096). The refunds of the fund are "Analiza" income without an operation.
        'przychody rozłożone' => SheetMapping::FUND,
        'fundusz wydatków nieregularnych' => SheetMapping::FUND,
        'pozostałe (premie, 13)' => 1,
        // The same Category as the premie: the only negative cell (grudzień 2021) moves into the premie cell of its
        // month; in Category 7 it would be the lone cell of its source and the planner would refuse.
        'międzyokresowe' => 1,
    ],

    // Part of a cell becomes a separate operation in another Category.
    'overrides' => [
        ['year' => 2023, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 683_107, 'category' => 6, 'description' => 'Zwrot podatku'],
        ['year' => 2024, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 582_320, 'category' => 6, 'description' => 'Zwrot podatku'],
        ['year' => 2025, 'month' => 2, 'label' => 'pozostałe', 'amount_minor_units' => 579_012, 'category' => 6, 'description' => 'Zwrot podatku'],
    ],

    'categories' => [
        1 => ['name' => 'Sebastian', 'type' => 'income'],
        2 => ['name' => 'Janeczka', 'type' => 'income'],
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
        14 => ['name' => 'Telefony', 'type' => 'expense'],
        15 => ['name' => 'Prąd', 'type' => 'expense'],
        16 => ['name' => 'Paliwo', 'type' => 'expense'],
        17 => ['name' => 'Eksploatacja auta', 'type' => 'expense'],
        18 => ['name' => 'Czesne', 'type' => 'expense'],
        19 => ['name' => 'Ubezpieczenia na życie', 'type' => 'expense'],
        20 => ['name' => 'Lekarze i badania', 'type' => 'expense'],
        21 => ['name' => 'Ubezpieczenie samochodu', 'type' => 'expense'],
        22 => ['name' => 'Komunikacja i parking', 'type' => 'expense'],
        23 => ['name' => 'Wyjścia i rozrywka', 'type' => 'expense'],
        24 => ['name' => 'Jedzenie poza domem', 'type' => 'expense'],
        25 => ['name' => 'Wakacje i wycieczki', 'type' => 'expense'],
        27 => ['name' => 'Ubrania', 'type' => 'expense'],
        28 => ['name' => 'Darowizny', 'type' => 'expense'],
        29 => ['name' => 'Rzeczy osobiste', 'type' => 'expense'],
        30 => ['name' => 'Edukacja', 'type' => 'expense'],
        31 => ['name' => 'Prezenty', 'type' => 'expense'],
        32 => ['name' => 'Rzeczy dla dzieci', 'type' => 'expense'],
        33 => ['name' => 'Poczta i urzędy', 'type' => 'expense'],
        34 => ['name' => 'Książki', 'type' => 'expense'],
        35 => ['name' => 'Remonty i naprawy', 'type' => 'expense'],
        36 => ['name' => 'Elektronika i AGD', 'type' => 'expense'],
        37 => ['name' => 'Wyposażenie', 'type' => 'expense'],
        39 => ['name' => 'Handel', 'type' => 'income'],
        42 => ['name' => 'Chemia i kosmetyki', 'type' => 'expense'],
        44 => ['name' => 'Internet', 'type' => 'expense'],
        45 => ['name' => 'Streaming', 'type' => 'expense'],
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
