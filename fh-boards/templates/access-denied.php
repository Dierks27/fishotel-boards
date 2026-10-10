<?php
/**
 * Template – Tester access only message.
 *
 * Shown to logged-in users who do not meet the capability/role configured
 * under FH Boards > Settings > Tester Access (boards-smtp-wpit-20).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="fhb-login-required fhb-access-denied">
    <p>The FisHotel Boards are open to beta testers only.</p>
    <p>If you are part of the beta program and can't get in, let us know and we'll sort it out.</p>
</div>
