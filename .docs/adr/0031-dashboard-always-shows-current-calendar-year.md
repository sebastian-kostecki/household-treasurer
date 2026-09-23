# Dashboard always shows the current calendar year

Dashboard answers “does anything need me now, and how much can I still spend this month”, so its Budget area always reads the User’s Budget for today’s calendar year, not the shell Budget year. The shell year control stays visible and keeps driving Budget and Agenda; Dashboard labels its Budget area with the year it shows, and its “open Budget” link switches the shell year to the current year first (the same `budget.select-year` move as ADR-0019). This deliberately breaks the app-shell story that every screen is about the shell year.

We rejected following the shell year (a past or future year on Dashboard mixes today’s Liquidity with another year’s plan and silently drops Month Remaining alerts, which only exist for the current year) and hiding or disabling the shell control on Dashboard (it is persistent chrome and its choice matters on the next Budget visit).
