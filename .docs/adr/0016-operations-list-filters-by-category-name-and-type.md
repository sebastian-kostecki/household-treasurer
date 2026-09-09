# Operations list filters by Category name and type, not identity

The Operations list slices Financial Operations by Category as a query on name and type so a copied name can be followed across Budget years. That pair is not a Category and does not reopen ADR-0014: records stay per Budget, a rename splits the history, and we rejected filtering by `category_id` (one year only) and a year-spanning Category identity.
