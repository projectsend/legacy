<?php
function upgrade_2026080701()
{
    global $dbh;

    /**
     * Records the second factor method a token was created for.
     *
     * Without it the code column is the only thing telling the two methods
     * apart, and the 0 that TOTP rows store as a marker is a value the email
     * verifier accepts, so a TOTP token could be redeemed on the email path
     * without a code.
     */
    $statement = $dbh->prepare("SHOW COLUMNS FROM " . TABLE_AUTHENTICATION_CODES . " LIKE 'method'");
    $statement->execute();

    if ($statement->rowCount() == 0) {
        $query = "ALTER TABLE `" . TABLE_AUTHENTICATION_CODES . "` ADD COLUMN `method` varchar(20) NOT NULL DEFAULT 'email'";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }

    /**
     * Tokens still waiting to be verified when the upgrade runs: a code of 0
     * was the marker createTotpToken() wrote, everything else is an email code.
     */
    $query = "UPDATE `" . TABLE_AUTHENTICATION_CODES . "` SET method = 'totp' WHERE code = 0";
    $statement = $dbh->prepare($query);
    $statement->execute();
}
