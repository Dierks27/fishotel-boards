<?php
/**
 * Admin view – Settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$delete_data  = get_option( 'fhb_delete_data_on_uninstall', false );
$required_cap = get_option( FHB_Constants::OPTION_REQUIRED_CAP, '' );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'fhb_settings' ); ?>

        <h2>Tester Access</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="fhb_required_capability">Required capability or role</label></th>
                <td>
                    <input type="text" class="regular-text code" id="fhb_required_capability" name="<?php echo esc_attr( FHB_Constants::OPTION_REQUIRED_CAP ); ?>" value="<?php echo esc_attr( $required_cap ); ?>" placeholder="e.g. fhb_beta_tester or edit_posts" />
                    <p class="description">
                        Only users with this WordPress capability or role slug can view and post on the Boards. Leave blank to allow any logged-in user (the current behaviour). Administrators always have access.
                    </p>
                </td>
            </tr>
        </table>

        <h2 style="color: #dc3232;">Danger Zone</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Data Removal</th>
                <td>
                    <label>
                        <input type="checkbox" name="fhb_delete_data_on_uninstall" value="1" <?php checked( $delete_data ); ?> />
                        Delete all FH Boards data when plugin is uninstalled
                    </label>
                    <p class="description" style="color: #dc3232;">
                        <strong>Warning:</strong> Checking this will permanently delete all topics, replies, subscriber data, and custom database tables when FH Boards is uninstalled. This action cannot be undone.
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
