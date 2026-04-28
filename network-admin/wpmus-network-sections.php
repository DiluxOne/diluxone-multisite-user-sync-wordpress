<?php


// class wpmus {

	function wpmus_networkmenu_items() {
 
		add_menu_page(
			'WPM User Sync', // page_title
			'WPM User Sync', // menu_title
			'manage_options', // capability
			'wpmus-networkhome', // menu_slug
			'wpmus_networkhome', // Callback function which displays the page
			'dashicons-admin-generic', // Icon
			100 // Position of the menu item in the menu.
		);

		add_submenu_page(
			'wpmus-networkhome', // Parent element
			'Network Sync Options', // Text in browser title bar
			'Network Sync Options', // Text to be displayed in the menu.
			'manage_options', // Capability
			'wpmus-networksyncoptions', // Page slug, will be displayed in URL
			'wpmus_networksyncoptions' // Callback function which displays the page
		);

		add_submenu_page(
			'wpmus-networkhome', // Parent element
			'Network Sync Actions', // Text in browser title bar
			'Network Sync Actions', // Text to be displayed in the menu.
			'manage_options', // Capability
			'wpmus-networksyncactions', // Page slug, will be displayed in URL
			'wpmus_networksyncactions' // Callback function which displays the page
		);
 
	}

	function wpmus_networkhome() {
    
		echo wpmus_generic_header();
		echo wpmus_network_home_content();
	  
	}

	function wpmus_networksyncoptions() {
	
		echo wpmus_generic_header();
		echo wpmus_network_syncoptions_settings();

	}

	function wpmus_networksyncactions() {
	
		echo wpmus_generic_header();
		echo wpmus_network_syncactions_options();

	}

    