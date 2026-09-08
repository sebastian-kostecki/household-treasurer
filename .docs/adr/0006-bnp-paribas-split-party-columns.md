# Split BNP Paribas party columns map to the other party

Current GOonline exports split Nadawca and Odbiorca instead of a single Nadawca / odbiorca column. Import accepts both layouts. For the split layout, Counterparty is the other party: Odbiorca on outflow, Nadawca on inflow, falling back to the remaining cell when the preferred one is empty. Concatenating both would put the User’s own account details into Counterparty.
