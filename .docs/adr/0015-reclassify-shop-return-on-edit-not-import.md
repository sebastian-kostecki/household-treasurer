# Reclassify shop-return credits on edit, not during Import

A bank credit that is money back from a purchase is an expense refund, not Income, but Import does not guess that. The User parks the credit as Income on any income Category, then reclassifies it on edit to an expense Category. We rejected Import hints, auto-typing credits as refunds, and pairing a credit to a specific earlier expense: classification after save is the correction path, and expense refund stays unlinked to the original purchase.
