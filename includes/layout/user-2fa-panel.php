<?php
/**
 * Two factor panel on the user and client edit screens.
 *
 * An account that loses its authenticator app, and its backup codes with it,
 * has no way back in on its own once two factor is required. Whoever is
 * allowed to edit the account can clear the app from here, and the person
 * enrolls again the next time they log in.
 *
 * Expects $panel_user, a \ProjectSend\Classes\Users object.
 */

if (empty($panel_user) || !is_a($panel_user, '\ProjectSend\Classes\Users')) {
    return;
}

if (!defined('CURRENT_USER_ID')) {
    return;
}

/**
 * Not a route to your own account: totp-setup.php already does that, and asks
 * for the password before it will. Nothing here could ask for one.
 */
if ($panel_user->id == CURRENT_USER_ID) {
    return;
}

if (!$panel_user->canUserEdit(CURRENT_USER_ID)) {
    return;
}

$panel_totp = new \ProjectSend\Classes\Totp();
$panel_totp_enabled = $panel_totp->isEnabledForUser($panel_user->id);
$panel_backup_codes = $panel_totp_enabled ? $panel_totp->getRemainingBackupCodesCount($panel_user->id) : 0;
?>
<div class="white-box">
    <div class="white-box-interior">
        <h3><?php _e('Two-factor authentication', 'cftp_admin'); ?></h3>

        <?php if (!$panel_totp_enabled) { ?>
            <p><?php _e('This account has no authenticator app set up.', 'cftp_admin'); ?></p>
            <?php
            if ((bool)get_option('two_factor_required', null, '0')) {
                echo system_message('info', __('Two-factor authentication is required, so this account will be asked to set up an authenticator app the next time it logs in.', 'cftp_admin'));
            }
            ?>
        <?php } else { ?>
            <p>
                <?php _e('This account signs in with an authenticator app.', 'cftp_admin'); ?>
                <?php echo sprintf(__('It has %s backup codes left.', 'cftp_admin'), (int)$panel_backup_codes); ?>
            </p>
            <p class="field_note form-text">
                <?php _e('Remove it only when the person has lost access to both their authenticator app and their backup codes. They will be asked to set up a new one the next time they log in, and the old codes stop working immediately.', 'cftp_admin'); ?>
            </p>
            <form method="post" action="<?php echo BASE_URI; ?>process.php?do=totp_reset" class="mt-2">
                <?php addCsrf(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int)$panel_user->id; ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('<?php echo html_output(__('Remove the authenticator app from this account?', 'cftp_admin')); ?>')">
                    <?php _e('Remove authenticator app', 'cftp_admin'); ?>
                </button>
            </form>
        <?php } ?>
    </div>
</div>
