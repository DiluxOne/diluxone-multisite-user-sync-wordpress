<?php

function wpmus_site_syncactions_options() {

    echo '<div class="wrap">
    <h3>Site Sync Actions</h3>
    <p>These actions let you sync all network users in your site.</p>';
        wp_nonce_field( 'wpmus-validate' );
        echo '
        <table class="form-table">
            <tr>
                <th scope="row">Sync from scratch</th>
                <td>
                    <form method="post" action="edit.php?action=wpmusSyncSiteSiteFromScratch">';
                    wp_nonce_field( 'wpmus-validate' );
                    echo '
                        <input id="test-settings" type="submit" value="Sync from scratch" class="button" >
                        <input id="blogid" type="hidden" value=';
                        echo get_current_blog_id();
                        echo '>
                        <input id="site" type="hidden" value="yes">
                    </form>
                    <p class="description">Add all network users in your site with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</p>
                </td>
            </tr>
        </table>';

}