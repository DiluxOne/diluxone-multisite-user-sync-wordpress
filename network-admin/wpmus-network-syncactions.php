<?php

function wpmus_network_syncactions_options() {
    global $wpdb;
    $sites = get_sites();

    echo '<div class="wrap">
    <h3>Network Sync Actions</h3>
    <p>These actions let you sync users & sites with several options.</p>';
        wp_nonce_field( 'wpmus-validate' );
        echo '
        <table class="form-table">
            <tr>
                <th scope="row">Sync from scratch</th>
                <td>
                    <form method="post" action="edit.php?action=wpmusSyncNetworkFromScratch">';
                    wp_nonce_field( 'wpmus-validate' );
                    echo '
                        <input id="test-settings" type="submit" value="Sync from scratch" class="button" >
                    </form>
                    <p class="description">Sync all sites with all users. Each site will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</p>
                </td>
            </tr>

            <tr>
            <th scope="row">Sync specific site</th>
            <td>
                <p>Please, select the site that you want sync users:</p>
                <br />
                <form method="post" action="edit.php?action=wpmusSyncNetworkSiteFromScratch">';
                wp_nonce_field( 'wpmus-validate' );

                    foreach ( $sites as $site ) {
                        echo '<input type="checkbox" name="listSites[]" value='. $site->blog_id .' />'. $site->domain, $site->path .' <br />';
                    }
                    echo '
                    <input id="network" type="hidden" value="yes">
                    <br />
                    <input id="test-settings" type="submit" value="Sync selected sites" class="button" >
                </form>
                <p class="description">All selected sites will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</p>
            </td>
        </tr>
        </table>';

}