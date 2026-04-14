<?php
/**
 * FHB_User_Profile – Adds "Receive FH Boards email notifications" checkbox to user profiles.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_User_Profile {

    public static function init() {
        add_action( 'show_user_profile', array( __CLASS__, 'render_field' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_field' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_field' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_field' ) );
    }

    /**
     * Render the checkbox on the user profile page.
     */
    public static function render_field( $user ) {
        $value = get_user_meta( $user->ID, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, true );
        ?>
        <h3>FH Boards</h3>
        <table class="form-table">
            <tr>
                <th><label for="fhb_email_notifications">Board Notifications</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="fhb_email_notifications" id="fhb_email_notifications" value="1" <?php checked( $value, '1' ); ?> />
                        Receive FH Boards email notifications
                    </label>
                    <p class="description">When enabled, you can subscribe to individual topics to receive email notifications about new replies.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the checkbox value.
     */
    public static function save_field( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
            return;
        }

        $value = isset( $_POST[FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS] ) ? '1' : '0';
        update_user_meta( $user_id, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, $value );
    }
}
