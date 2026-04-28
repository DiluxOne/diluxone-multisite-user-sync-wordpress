<?php

function wpmus_site_home_content() {
    
    global $sd_active_tab;

    if (isset($_GET['tab'])) {
        $sd_active_tab = sanitize_text_field($_GET['tab']);
        
    }else{
        $sd_active_tab = 'welcome';

    }
    
    ?>

    <h2 class="nav-tab-wrapper">
    <?php
        do_action( 'wpmus_site_home_tabs' );
    ?>
    </h2>
    <?php
        do_action( 'wpmus_site_home_contents' );
    
}

function wpmus_site_home_welcome_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'welcome' || '' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'admin.php?page=wpmus-sitehome&tab=welcome' ); ?>"><?php _e( 'Welcome', 'sd' ); ?> </a>
    <?php
}

function wpmus_site_home_concepts_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'concepts' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'admin.php?page=wpmus-sitehome&tab=concepts' ); ?>"><?php _e( 'Concepts', 'sd' ); ?> </a>
    <?php
}

function wpmus_site_home_about_tab(){
    global $sd_active_tab; ?>
    <a class="nav-tab <?php echo $sd_active_tab == 'about' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'admin.php?page=wpmus-sitehome&tab=about' ); ?>"><?php _e( 'About', 'sd' ); ?> </a>
    <?php
}

function wpmus_site_home_welcome_content() {
    global $sd_active_tab;
    if ( '' || 'welcome' != $sd_active_tab )
        return;
    ?>

    <h3><?php _e( 'Welcome to Site "WPM User Sync" Plugin', 'sd' ); ?></h3>
    <p>Thank you for choosing WPM User Sync (which actually means "WordPress Multi-Site User Synchronization").</p>
    <p>If you are new using this plugin, we recommend you to check the basic synchronization concepts. If this is the first time you use the plugin, you can also do your first full sync.</p>

    <a href="<?php echo admin_url( 'admin.php?page=wpmus-sitehome&tab=concepts' ); ?>" class="cuadrado">1. Review basic concepts</a>
    <a href="<?php echo admin_url( 'admin.php?page=wpmus-sitesyncactions' ); ?>" class="cuadrado">2. Complete the initial Users Sync</a>
    <a href="<?php echo admin_url( 'admin.php?page=wpmus-sitehome&tab=about' ); ?>" class="cuadrado">3. Meet the Authors & Support Us</a>

    <p>Do you want online help? Check our <a href="https://pablodiloreto.com/wpm-user-sync/">site</a>.</p>

    <?php
}

function wpmus_site_home_concepts_content() {
    global $sd_active_tab;
    if ( 'concepts' != $sd_active_tab )
        return;
    ?>

    <h3><?php _e( 'Site User Sync Concepts', 'sd' ); ?></h3>
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
    <p>Your Network Admin can configure all these 3 triggers from network level options.</p>

    <h4>What can configure an administrator at site level?</h4>
    <p>At site level you can not configure any option. All options must be configured at Network Level. But you, as Site Admin, can execute the following action:</p>
    <ul>
        <li>- <strong>Sync from scratch</strong>: Add all network users in your site with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.</li>
    </ul>

    <h4>Can I avoid automatic actions and only act with manual actions?</h4>
    <p>Yes! But it must be configured by a Network Level Admin.</p>
    <?php
}

function wpmus_site_home_about_content() {
global $sd_active_tab;
if ( 'about' != $sd_active_tab )
    return;
?>

<h3><?php _e( 'About', 'sd' ); ?></h3>
<p>This plugin was developer by <strong>Pablo Ariel Di Loreto</strong>:</p>
<ul>
    <li>- <a href="https://www.linkedin.com/in/pablodiloreto/" target="_blank">LinkedIn Contact</a>.</li>
    <li>- <a href="https://pablodiloreto.com/" target="_blank">Personal Blog</a>.</li>
    <li>- <a href="https://pablodiloreto.com/wpm-user-sync/" target="_blank">Plugin Homepage</a>.</li>
</ul>
<?php
}