<?php


// class wpmus {

	function wpmus_sitemenu_items() {
 
		add_menu_page(
			'WPM User Sync', // page_title
			'WPM User Sync', // menu_title
			'manage_options', // capability
			'wpmus-sitehome', // menu_slug
			'wpmus_sitehome', // Callback function which displays the page
			'dashicons-admin-generic', // Icon
			100 // Position of the menu item in the menu.
		);

		add_submenu_page(
			'wpmus-sitehome', // Parent element
			'Site Sync Actions', // Text in browser title bar
			'Site Sync Actions', // Text to be displayed in the menu.
			'manage_options', // Capability
			'wpmus-sitesyncactions', // Page slug, will be displayed in URL
			'wpmus_sitesyncactions' // Callback function which displays the page
		);
 
	}

	function wpmus_sitehome() {
    
		echo wpmus_generic_header();
		echo wpmus_site_home_content();
	  
	}

	function wpmus_sitesyncactions() {
	
		echo wpmus_generic_header();
		echo wpmus_site_syncactions_options();

	}

    