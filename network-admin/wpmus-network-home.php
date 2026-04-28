<?php

function wpmus_network_home_content() {
    
    global $sd_active_tab;

    if (isset($_GET['tab'])) {
        $sd_active_tab = sanitize_text_field($_GET['tab']);
        
    }else{
        $sd_active_tab = 'welcome';

    }
    
    ?>

    <h2 class="nav-tab-wrapper">
    <?php
        do_action( 'wpmus_network_home_tabs' );
    ?>
    </h2>
    <?php
        do_action( 'wpmus_network_home_contents' );
    
}

function wpmus_network_home_welcome_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'welcome' || '' ? 'nav-tab-active' : ''; ?>" href="<?php echo network_admin_url( 'admin.php?page=wpmus-networkhome&tab=welcome' ); ?>"><?php _e( 'Welcome', 'sd' ); ?> </a>
    <?php
}

function wpmus_network_home_concepts_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'concepts' ? 'nav-tab-active' : ''; ?>" href="<?php echo network_admin_url( 'admin.php?page=wpmus-networkhome&tab=concepts' ); ?>"><?php _e( 'Concepts', 'sd' ); ?> </a>
    <?php
}

function wpmus_network_home_about_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'about' ? 'nav-tab-active' : ''; ?>" href="<?php echo network_admin_url( 'admin.php?page=wpmus-networkhome&tab=about' ); ?>"><?php _e( 'About', 'sd' ); ?> </a>
    <?php
}

function wpmus_network_home_welcome_content() {
    global $sd_active_tab;
    if ( '' || 'welcome' != $sd_active_tab )
        return;
    ?>

    <h3><?php _e( 'Welcome to Network "WPM User Sync" Plugin', 'sd' ); ?></h3>
    <p>Thank you for choosing WPM User Sync (which actually means "WordPress Multi-Site User Synchronization"). Thank you for choosing WPM User Sync (which actually means "WordPress Multi-Site User Synchronization"). Follow the next steps to getting start synchronizing:</p>

    <a href="<?php echo network_admin_url( 'admin.php?page=wpmus-networkhome&tab=concepts' ); ?>" class="cuadrado">1. Review basic concepts</a>
    <a href="<?php echo network_admin_url( 'admin.php?page=wpmus-networksyncactions' ); ?>" class="cuadrado">2. Complete the initial Users Sync</a>
    <a href="<?php echo network_admin_url( 'admin.php?page=wpmus-networksyncoptions' ); ?>" class="cuadrado">3. Check all WPM User Sync options</a>
    <a href="<?php echo network_admin_url( 'admin.php?page=wpmus-networkhome&tab=about' ); ?>" class="cuadrado">4. Meet the Authors & Support Us</a>

    <p>Do you want online help? Check our <a href="https://pablodiloreto.com/wpm-user-sync/">site</a>.</p>
    
    <?php
    
}

function wpmus_network_home_concepts_content() {
    global $sd_active_tab;
    if ( 'concepts' != $sd_active_tab )
        return;
    ?>

    <h3><?php _e( 'User Sync Concepts', 'sd' ); ?></h3>
    <p>WPM User Sync has some simple but important concepts. Knowning all them will help you to get a better experience with the tool.</p>

    <h4>What exactly does this plugin do?</h4>
    <p>WPM User Sync is a plugin that enable the user synchronization in your Wordpress Multisite, that is a type of WordPress installation that allows you to create and manage a network of multiple websites from a single WordPress dashboard.</p>
    <p>Key concepts:</p>
    <ul>
        <li>- WPM User sync is a plugin, not a core feature of WordPress. It was built by external developers to WordPress. However, it goes through a detailed testing process to ensure smooth operation as it interacts with core aspects of the CMS.</li>
        <li>- In out-of-the-box WordPress multisite setup, when you create a new user, it never sync to other sites in your network. Also, when you create a new site in your network, no users are synced to this new site. This means that you must manually register or associate users to your site, or your new site with your users. This is a tedious and manual process.</li>
        <li>- This plugin bring you the possibility tu automate all this scenarios: a) when you create a new user, this user can be synced to all existing sites in your network; b) when you create a new site, all users can be automatic synced to it; c) when we change an user role in one site, you can configure to replicate this change to all sites in your network; d) if you do not want automation, with this plugin you can do all previous things in manual mode :-).</li>
        <li>- Last but not least, when we talk about 'user synchronization', we never duplicate user data. The user is only one, and the same identity is the one that is added to the sites in a reference model. If you are using "SUBDOMAIN_INSTALL" option (that is, each site on your network will be a subdomain) and you want "single-sign on experience", you should configure some cookies aspects in your WP-CONFIG. Check plugin's website help for more information.</li>
    </ul>

    <h4>What is a trigger? Which ones exist here?</h4>
    <p>In WPM User Sync you will can configure some triggers to automate user sync. A trigger is procedural code that is automatically executed in response to certain events, and in the particular case of WPM User Sync & WordPress, to one of the following events:</p>
    <ul>
        <li>- <strong>New user creation</strong>: when an user register in your site, or an admin create a new one.</li>
        <li>- <strong>New site creation</strong>: when an admin or authorized user create a new site in your network.</li>
        <li>- <strong>User role edited in one site</strong>: when you edit a user role in one of your network sites.</li>
    </ul>
    <p>Its very important to remember that you can configure all these 3 triggers from network level options.</p>

    <h4>What kind of options do I have at the network level?</h4>
    <p>At network level you can configure the 3 triggers that we descripted in the past:</p>
    <ul>
        <li>- <strong>New Site Automatic Sync</strong>: When a new site is created in the network, all users in the database will be added to this new site with default site role. If no default role is configured, "subscriber" role will be added.</li>
        <li>- <strong>New User Automatic Sync</strong>: When a new user is created in the network, will be added to all sites in the database with each default site role. If no default role is configured, "subscriber" role will be added.</li>
        <li>- <strong>Set User Role Automatic Sync</strong>: When an user role change is detected in any site (for example change an user to administrator of an specific site) this change will be replicated to all other sites (in the other sites will be administrator, too).</li>
    </ul>
    <p>Also, you can execute the following actions:</p>
    <ul>
        <li>- <strong>Sync from scratch</strong>: Sync all sites with all users. Each site will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</li>
        <li>- <strong>Sync specific site</strong>: All selected sites will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</li>
    </ul>

    <h4>What can configure an administrator at site level?</h4>
    <p>At site level you can not configure any option. But you can execute the following action:</p>
    <ul>
        <li>- <strong>Sync from scratch</strong>: Add all network users in your site with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</li>
    </ul>

    <h4>Can I avoid automatic actions and only act with manual actions?</h4>
    <p>Yes! You can. Disable all triggers at network level & you will allow to execute only manual actions.</p>

    <?php
}

function wpmus_network_home_about_content() {
global $sd_active_tab;
if ( 'about' != $sd_active_tab )
    return;
?>

<h3><?php _e( 'About WPM User Sync Plugin', 'sd' ); ?></h3>
<p>This plugin was developer by <strong>Pablo Ariel Di Loreto</strong>:</p>
<ul>
    <li>- <a href="https://www.linkedin.com/in/pablodiloreto/" target="_blank">LinkedIn Contact</a>.</li>
    <li>- <a href="https://pablodiloreto.com/" target="_blank">Personal Blog</a>.</li>
    <li>- <a href="https://pablodiloreto.com/wpm-user-sync/" target="_blank">Plugin Homepage</a>.</li>
</ul>
<?php
}