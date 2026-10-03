===========================================================================
 TWITTPAY - Smart SMM Panel v4 deposit method
===========================================================================

 WHERE IT GOES
   Extract this zip at your panel root - the folder that has app/ and assets/ in
   it. These files land in place:

     app/modules/add_funds/controllers/twittpay.php
     app/modules/add_funds/libraries/twittpayapi.php
     app/modules/add_funds/views/twittpay/index.php
     app/modules/admin/views/payments/integrations/twittpay.php
     app/modules/payments/views/integrations/twittpay.php
     assets/twittpay.png
     assets/payments/twittpay.png

   database.sql and this README sit at the top of the zip. They are not part of
   the panel - delete them from the server after you are done, or do not upload
   them at all.

 INSTALL
   1. Upload and extract at the panel root as above.
   2. Import database.sql in phpMyAdmin, into your panel's database. It adds one
      row to the `payments` table and touches nothing else.
   3. Admin -> Payments -> open "Bkash/Nagad/Rocket/Upay" and fill in:


        Brand Key           from your gateway dashboard, under Brands

        USD to BDT Rate   only used when your panel currency is not BDT

   4. Set the minimum and maximum, set it Active, and make a small test deposit.

 HOW IT WORKS
   * The user picks the method on the Add Funds page, types an amount and pays.
   * A transaction log row is written first with status 0, then the payment is
     created and the user is sent to the gateway's checkout page.
   * Both the returning user and the gateway's webhook come back to
     add_funds/twittpay/complete.
   * Nothing on that request is trusted. The transaction id is read from it and
     the payment is then verified against the API.
   * COMPLETED credits the deposit, but only while its log row is still status 0 -
     so the webhook and the return cannot credit the same deposit twice.
   * PENDING credits nothing and leaves the row alone. The user has sent the money
     and your merchant has not approved it. The gateway calls again with the
     answer, and that call credits the deposit. The user is sent back to the Add
     Funds page, not to the failure page.

 CURRENCY
   The gateway charges BDT.

   * A panel running in BDT sends the amount as it is.
   * Any other panel currency is multiplied by the USD to BDT Rate, and the
     deposit's own amount and currency ride along in metadata - so the balance the
     user gets is in your panel currency, exactly what they asked to deposit.

 WHAT TO WATCH
   * add_funds/twittpay/complete must be reachable from the internet. Your
     gateway's server calls it directly.
   * The transaction fee percentage is stored but not deducted, exactly as in the
     original module. If you want a fee, add it to the amount you ask for.
   * Refunds are not done through the API. Refund on the gateway side, then adjust
     the user's balance by hand.

 FIXES OVER THE ORIGINAL
   * The PipraPay version compared an Brand Key sent in a webhook header. This
     gateway's webhook is not signed and sends no key, so that check would have
     refused every real call. It is gone - verification against the API does the
     job, because a made-up transaction id simply does not verify.
   * The original threw a raw Exception out of the webhook when a field was
     missing, which answers the gateway with a 500 and makes it retry forever.
     Every path now answers cleanly.
   * The original had nothing for the user's own return: an empty request body
     sent them back to Add Funds with no deposit. The return now verifies too.
   * A pending payment was treated as a failure. It is now left alone for the next
     webhook.
   * The original set a $this->currency_code property that was never declared,
     which is a deprecation notice on PHP 8.2. It is declared now.
   * The original showed the raw API error to the user. An error string can carry
     your Brand Key back out, so this port shows a plain message.

 CHECKED
   The PHP was checked with a lexer that balances braces only inside real PHP
   code. PHP itself was NOT run - there is no PHP binary on the machine this was
   built on, so php -l was never executed. Test it on a staging install first.
