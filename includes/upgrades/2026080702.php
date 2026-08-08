<?php
function upgrade_2026080702()
{
    /**
     * The generic OIDC provider matches an identity onto an account by email
     * address, so a provider that hands over an address it never verified is
     * enough to sign in as any user holding that address. Default to refusing
     * those logins; the option exists for providers that do not send the
     * email_verified claim at all.
     */
    add_option_if_not_exists('oidc_require_verified_email', '1');
}
