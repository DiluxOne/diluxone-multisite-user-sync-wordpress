<?php

function wpmus_network_syncoptions_settings() {
    global $wpmus_newSiteSync;
    global $wpmus_newUserSync;
    global $wpmus_setUserRoleSync;

    echo '<div class="wrap">
    <h3>Network Configuration</h3>
    <p>These settings let you customize the sync behavior.</p>
    <form method="post" action="edit.php?action=wpmusSaveGlobalConfig">';
        wp_nonce_field( 'wpmus-validate' );
        echo '
        <table class="form-table">
            <tr>
                <th scope="row">New Site Automatic Sync</th>
                <td>
                    <label><input name="wpmus_newSiteSync" type="checkbox" value="yes" ' . checked('yes', $wpmus_newSiteSync, false ) . '> Sync new site with all users</label>
                    <p class="description">When a new site is created in the network, all users in the database will be added to this new site with default site role. If no default role is configured, "subscriber" role will be added.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">New User Automatic Sync</th>
                <td>
                    <label><input name="wpmus_newUserSync" type="checkbox" value="yes" ' . checked('yes', $wpmus_newUserSync, false ) . '> Sync new users with all sites</label>
                    <p class="description">When a new user is created in the network, will be added to all sites in the database with each default site role. If no default role is configured, "subscriber" role will be added.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Set User Role Automatic Sync</th>
                <td>
                    <label><input name="wpmus_setUserRoleSync" type="checkbox" value="yes" ' . checked('yes', $wpmus_setUserRoleSync, false ) . '> Sync new user roles to all sites</label>
                    <p class="description">When an user role change is detected in any site (for example change an user to administrator of an specific site) this change will be replicated to all other sites (in the other sites will be administrator, too).</p>
                </td>
            </tr>
        </table>';

        submit_button();
    echo '</form></div>';

}