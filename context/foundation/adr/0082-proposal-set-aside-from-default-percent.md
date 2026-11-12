# A Proposal writes its Set-aside from the Default Set-aside Percent

ADR-0033:5 made a Proposal copy the positive Set-aside of its source month. The User saves about 10 % of income every month (PRD v3), and the copied amount is only that when last month's figure happened to be right: it drifts with every change of income and starts at 0 where there is no source. This ADR adds one setting of the User, the Default Set-aside Percent, and makes the Proposal start from it.

Rule. The Default Set-aside Percent is a whole number from 0 to 100, or none. When it is set to p, the Set-aside of a Proposal is a share of the sum P of the planned income of that Proposal (the amounts of the income Budget Lines in that Proposal, archived lines that the Proposal leaves out not counted), rounded down to a whole złoty: in Minor units, intdiv(P × p, 10000) × 100. It replaces the copy of a positive Set-aside from the source. ADR-0033:5 applies only when no percent is set: then a Proposal copies the positive Set-aside of its source and a Draw or no source gives 0, as before. The percent belongs to the User, so it also applies to the Proposal of the previous Budget when that serves as the source of January.

Examples. P = 8 437,55 zł at 10 % gives 843,00 zł. P = 9 000 zł at 10 % gives 900,00 zł. At 0 % the Set-aside of a Proposal is 0, whatever the source holds.

Relation to ADR-0033 and ADR-0062. Only the starting amount of a Proposal changes. An Approved Month Plan keeps the amount that was sent at approval, and an amount typed by hand before approval stays as typed; changing the percent later moves no approved plan. The To Spend formula, the rule of „odkładam” and „dobieram” (a Draw, FR-013) and the warning for |Draw| above Spendable Liquidity are unchanged. The percent is not recomputed while the User edits incomes in the Proposal form; the figure shown is the reading from the server, and a new reading follows the save. Change mode (ADR-0062) does not use the percent.

We rejected:

- the percent only when the Proposal has no source (the setting would do nothing after the first month, and the copied amount would keep the drift);
- the greater of the percent and the copied amount (a single high month would stay in every later Proposal and the User could not lower the starting amount with the setting);
- a percent per Budget (a second place to set it, and January would need a rule for which Budget wins);
- a decimal percent and rounding to the grosz (precision that a monthly saving habit does not have, and a Set-aside of 843,76 zł is harder to read than 843 zł);
- recomputing live while incomes are edited in the form (a copy of the calculation in TypeScript beside the server reading);
- a migration that sets 10 % for everyone (the percent would silently replace the copied amount of users who set their Set-aside by hand).
