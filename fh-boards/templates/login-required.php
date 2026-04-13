<?php
/**
 * Template – Login required message.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="fhb-login-required">
    <p>Please <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">log in</a> to access the FisHotel Boards.</p>
</div>
