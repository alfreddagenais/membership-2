<?php
/**
 * Add-On controller for: Hustle
 *
 * @since  1.1.2
 *
 * @package Membership2
 * @subpackage Controller
 */
class MS_Addon_Hustle extends MS_Addon {

	/**
	 * The Add-on ID
	 *
	 * @since  1.1.2
	 */
	const ID = 'hustle';

	/**
	 * Ajax action to get lists
	 *
	 * @since 1.1.2
	 */
	const AJAX_ACTION_GET_ISTS = 'ms_hustle_get_lists';

	/**
	 * Save provider details
	 *
	 * @since 1.1.2
	 */
	const AJAX_ACTION_SAVE_PROVIDER = 'ms_hustle_save_provider';
	
	/**
	 * Current provider
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Checks if the current Add-on is enabled
	 *
	 * @since  1.1.2
	 * @return bool
	 */
	static public function is_active() {
		if ( ! self::is_hustle_active()
			&& MS_Model_Addon::is_enabled( self::ID )
		) {
			$model = MS_Factory::load( 'MS_Model_Addon' );
			$model->disable( self::ID );
		}
		return MS_Model_Addon::is_enabled( self::ID );
	}


	/**
	 * Initializes the Add-on. Always executed.
	 *
	 * @since  1.1.2
	 */
	public function init() {
		if ( self::is_active() ) {
			$settings 			= MS_Factory::load( 'MS_Model_Settings' );
			$hustle_provider 	= $settings->get_custom_setting( 'hustle', 'hustle_provider' );
			if ( $hustle_provider && !empty( $hustle_provider ) ) {
				$this->provider = $hustle_provider;
			} else {
				$this->provider = false;
			}

			$this->add_filter(
				'ms_controller_settings_get_tabs',
				'settings_tabs',
				10, 2
			);

			$this->add_action(
				'ms_controller_settings_enqueue_scripts_' . self::ID,
				'enqueue_scripts'
			);

			$this->add_filter(
				'ms_view_settings_edit_render_callback',
				'manage_render_callback',
				10, 3
			);

			// Watch for REGISTER event: Subscribe user to list.
			$this->add_action(
				'ms_model_event_'. MS_Model_Event::TYPE_MS_REGISTERED,
				'subscribe_registered',
				10, 2
			);

			// Watch for SIGN UP event: Subscribe user to list.
			$this->add_action(
				'ms_model_event_'. MS_Model_Event::TYPE_MS_SIGNED_UP,
				'subscribe_members',
				10, 2
			);

			// Watch for DEACTIVATE event: Subscribe user to list.
			$this->add_action(
				'ms_model_event_'. MS_Model_Event::TYPE_MS_DEACTIVATED,
				'subscribe_deactivated',
				10, 2
			);

			$this->add_ajax_action( self::AJAX_ACTION_GET_ISTS, 'get_provider_lists' );
			$this->add_ajax_action( self::AJAX_ACTION_SAVE_PROVIDER, 'save_provider_details' );

		}
	}


	/**
	 * Returns the Add-on ID (self::ID).
	 *
	 * @since  1.1.2
	 * @return string
	 */
	public function get_id() {
		return self::ID;
	}

	/**
	 * Returns true, when the WP_reCaptcha plugin is activated.
	 *
	 * @since  1.1.2
	 * @return bool
	 */
	static public function is_hustle_active() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( is_plugin_active( 'hustle/opt-in.php' ) || 
			is_plugin_active( 'wordpress-popup/popover.php' ) ) {
			
			return true;
		}
		return false;
	}


	/**
	 * Registers the Add-On
	 *
	 * @since  1.1.2
	 * @param  array $list The Add-Ons list.
	 * @return array The updated Add-Ons list.
	 */
	public function register( $list ) {
		$plugin_url 		= MS_Plugin::instance()->url;
		$list[ self::ID ] 	= (object) array(
			'name' 				=> __( 'Hustle Integration', 'membership2' ),
			'description' 		=> __( 'Add additional Hustle email providers', 'membership2' ),
			'icon' 				=> $plugin_url . 'app/assets/images/hustle.png',
		);

		if ( ! self::is_hustle_active() ) {
			$list[ self::ID ]->description .= sprintf(
				'<br /><b>%s</b>',
				__( 'Activate Hustle to use this Add-on', 'membership2' )
			);
			$list[ self::ID ]->action = '-';
		}

		return $list;
	}


	/**
	 * Add hustle settings tab in settings page.
	 *
	 * @since  1.1.2
	 *
	 * @filter ms_controller_membership_get_tabs
	 *
	 * @param  array $tabs The current tabs.
	 * @param  int $membership_id The membership id to edit
	 * @return array The filtered tabs.
	 */
	public function settings_tabs( $tabs ) {
		$tabs[ self::ID  ] = array(
			'title' => __( 'Hustle', 'membership2' ),
			'url' 	=> MS_Controller_Plugin::get_admin_url(
				'settings',
				array( 'tab' => self::ID )
			),
		);

		return $tabs;
	}


	/**
	 * Enqueue admin scripts in the settings screen.
	 *
	 * @since  1.1.2
	 */
	public function enqueue_scripts() {
		$data = array(
			'ms_init' 		=> array( 'view_settings_hustle' ),
			'error_saving' 	=> __( 'Error saving details. Please ensure that all required fields are entered', 'membership2' ),
			'error_fetching'=> __( 'Error fetching details. Please ensure that provider details are entered', 'membership2' )
		);

		lib3()->ui->data( 'ms_data', $data );
		wp_enqueue_script( 'ms-admin' );
	}


	/**
	 * Add hustle views callback.
	 *
	 * @since  1.1.2
	 *
	 * @filter ms_view_settings_edit_render_callback
	 *
	 * @param  array $callback The current function callback.
	 * @param  string $tab The current membership rule tab.
	 * @param  array $data The data shared to the view.
	 * @return array The filtered callback.
	 */
	public function manage_render_callback( $callback, $tab, $data ) {
		if ( self::ID == $tab ) {
			$view 		= MS_Factory::load( 'MS_Addon_Hustle_View' );
			$view->data = $data;
			$callback 	= array( $view, 'render_tab' );
		}

		return $callback;
	}

	/**
	 * Get the hustle providers
	 *
	 * @since  1.1.2
	 *
	 * @return array
	 */
	public static function hustle_providers() {
		$hustle_providers = array();
		if ( self::is_active() ) {
			global $hustle;
			$providers 				= $hustle->get_providers();
			$hustle_providers[] 	= __( 'Select a provider', 'membership2' );
			$supported_providers 	= self::hustle_provider_classes();
			$supported_providers 	= array_keys( $supported_providers );
			foreach ( $providers as $provider ) {
				if ( in_array( $provider['id'], $supported_providers ) ) {
					continue;
				} 
				$hustle_providers[ $provider['id'] ] = $provider['name'];
			}
		}
		return $hustle_providers;
	}

	/**
	 * List of all configured provider classes
	 *
	 * @return array
	 */
	private static function hustle_provider_classes() {
		return apply_filters( 'ms_hustle_provider_classes',  array(
			'activecampaign' 	=> 'MS_Addon_Hustle_Provider_Activecampaign',
			'campaignmonitor' 	=> 'MS_Addon_Hustle_Provider_Campaignmonitor',
			'convertkit' 		=> 'MS_Addon_Hustle_Provider_Convertkit',
			'getresponse' 		=> 'MS_Addon_Hustle_Provider_GetResponse',
			'infusionsoft' 		=> 'MS_Addon_Hustle_Provider_Infusionsoft',
			'mad_mimi' 			=> 'MS_Addon_Hustle_Provider_Madmimi',
			'mautic' 			=> 'MS_Addon_Hustle_Provider_Mautic',
			'sendinblue' 		=> 'MS_Addon_Hustle_Provider_Sendinblue'
		) );
	}

	/**
	 * Get provider api class by provider id
	 *
	 * @return object|bool
	 */
	public static function get_hustle_provider( $provider ) {
		$providers = self::hustle_provider_classes();
		if ( isset ( $providers[$provider] ) ) {
			$provider_class =  $providers[$provider];
			try {
				$provider_instance = new $provider_class;
				if ( is_a( $provider_instance, 'MS_Addon_Hustle_Provider' ) ) {
					return $provider_instance;
				}
			} catch( Exception $e ) {
				$this->log( $e->getMessage() );
			}
		}
		return false;
	}

	/**
	 * Subscription list types
	 * The different type of lists
	 *
	 * @return array
	 */
	public static function subscription_list_types() {
		return array(
			'registered' 	=> __( 'Registered users mailing list (not members)', 'membership2' ),
			'members'		=> __( 'Members mailing list', 'membership2' ),
			'deactivated' 	=> __( 'Deactivated memberships mailing list', 'membership2' )
		);
	}

	/**
	 * Refreshes provider account details after the account creds are added and submitted
	 *
	 * @since 1.1.2
	 *
	 * @return application/json
	 */
	public function get_provider_lists() {
		global $hustle;
		Opt_In_Utils::validate_ajax_call( "refresh_provider_details" );

		$provider_id =  filter_input( INPUT_POST, "optin_provider_name" );

        if ( empty( $provider_id ) ) {
			wp_send_json_error( __("Invalid provider", "membership2" ) );
		} 

		$api_key =  filter_input( INPUT_POST, "optin_api_key" );
        /**
         * @var $provider Opt_In_Provider_Interface
         */
		$provider = Opt_In::get_provider_by_id( $provider_id );
		
		/**
         * @var $provider Opt_In_Provider_Abstract
         */
		$provider = Opt_In::provider_instance( $provider );
		 
		$provider->set_arg( "api_key", $api_key );

		if( filter_input( INPUT_POST, "optin_secret_key" ) )
			$provider->set_arg( "secret", filter_input( INPUT_POST, "optin_secret_key" ) );
		if( filter_input( INPUT_POST, "optin_username" ) )
			$provider->set_arg( "username", filter_input( INPUT_POST, "optin_username" ) );
		if ( filter_input( INPUT_POST, "optin_password" ) )
			$provider->set_arg( "password", filter_input( INPUT_POST, "optin_password" ) );

		if( filter_input( INPUT_POST, "optin_account_name" ) )
			$provider->set_arg( "account_name", filter_input( INPUT_POST, "optin_account_name" ) );

		if( filter_input( INPUT_POST, "optin_url" ) )
			$provider->set_arg( "url", filter_input( INPUT_POST, "optin_url" ) );

		do_action( 'ms_hustle_get_provider_lists', $provider, $provider_id );
		$options = $provider->get_options( false );

		if ( !is_wp_error( $options ) ) {
			$html = "";

			$separator = array(
				'type' => MS_Helper_Html::TYPE_HTML_SEPARATOR,
			);
			
			$subscription_options = self::subscription_list_types();

			foreach ( $subscription_options as $k => $subscription_option ) {
				$html .= $subscription_option;
				foreach ( $options as $key => $option ) {
					if ( $option['type'] === 'select' ) {
						$name 			= $option['name'];
						$option['name'] = "mc_hustle[$k][$name]";
					} else if ( $option['type'] === 'text' ) {
						$name 			= $option['name'];
						$option['name'] = "mc_hustle[$k][$name]";
					}
					$option = apply_filters( "ms_hustle_optin_filter_optin_options", $option, $provider_id );
					$html 	.= $hustle->render( "general/option", array_merge( $option, array( "key" => $key ) ), true );
				}
				$html .= MS_Helper_Html::html_element( $separator, true );
			} 
            
			
			$update_btn = array(
				'id' 			=> 'btn-ms-hustle-save',
				'type' 			=> MS_Helper_Html::INPUT_TYPE_BUTTON,
				'value' 		=> __( 'Save', 'membership2' ),
				'button_value' 	=> wp_create_nonce( 'save_provider_details' ),
				'button_type' 	=> 'submit',
				'class'			=> 'ms_optin_save_provider_details',
			);

			
			$html .= MS_Helper_Html::html_element( $separator, true );
			$html .= MS_Helper_Html::html_element( $update_btn, true );

            wp_send_json_success( $html );
        } else {
            /**
             * @var WP_Error $options
             */
            wp_send_json_error( implode( "<br/>", $options->get_error_messages() ) );
        }
	}

	/**
	 * Save the provider details
	 *
	 * @since 1.1.2
	 *
	 * @return application/json
	 */
	public function save_provider_details() {
		Opt_In_Utils::validate_ajax_call( "save_provider_details" );

		$provider_id =  filter_input( INPUT_POST, "optin_provider_name" );
		
		if ( empty( $provider_id ) ) {
			wp_send_json_error( __( "Invalid provider", "membership2" ) );
		} 

		$settings 	= MS_Factory::load( 'MS_Model_Settings' );
		$details 	= array();
		if ( filter_input( INPUT_POST, "optin_api_key" ) ) {
			$details["optin_api_key"] 		= filter_input( INPUT_POST, "optin_api_key" );
		}
		if ( filter_input( INPUT_POST, "optin_secret_key" ) ) {
			$details["optin_secret_key"] 	= filter_input( INPUT_POST, "optin_secret_key" );
		}
		if ( filter_input( INPUT_POST, "optin_username" ) ) {
			$details["optin_username"] 		= filter_input( INPUT_POST, "optin_username" );
		}
		if ( filter_input( INPUT_POST, "optin_password" ) ) {
			$details["optin_password"] 		= filter_input( INPUT_POST, "optin_password" );
		}
		if ( filter_input( INPUT_POST, "optin_account_name" ) ) {
			$details["optin_account_name"] 	= filter_input( INPUT_POST, "optin_account_name" );
		}
		if ( filter_input( INPUT_POST, "optin_url" ) ) {
			$details["optin_url"] 			= filter_input( INPUT_POST, "optin_url" );
		}
		
		$details["lists"] 					= $_POST['mc_hustle'];

		$details = apply_filters( 'ms_hustle_provider_details', $details, $provider_id );

		$settings->set_custom_setting( 'hustle', $provider_id , $details );
		$settings->save();

		wp_send_json_success( __( 'Email provider settings saved', 'membership2' ) );
	}

	/**
	 * Get provider with details
	 *
	 * @return bool|array
	 */
	protected function get_provider_with_details() {
		if ( $this->provider ) {
			$settings 			= MS_Factory::load( 'MS_Model_Settings' );
			$provider_details 	= $settings->get_custom_setting( 'hustle', $this->provider );
			$provider_class 	= self::get_hustle_provider( $this->provider );
			if ( $provider_class ) {
				if ( $provider_details && is_array( $provider_details ) && is_array( $provider_details['lists'] ) ) {
					return array( $provider_class, $provider_details['lists'] );
				}
			}
		}
		return false;
	}


	/**
	 * A new user registered (not a Member yet).
	 *
	 * @since  1.1.2
	 * @param  mixed $event
	 * @param  mixed $member
	 */
	public function subscribe_registered( $event, $member ) {
		
		$provider_details = $this->get_provider_with_details();
		if ( is_array( $provider_details ) ) {
			list( $provider_class, $lists ) = $provider_details;
			if ( isset( $lists['registered'] ) ) {
				$list_id = $lists['registered']['value'];
				if ( $list_id ) {
					if ( ! $provider_class->is_user_subscribed( $member->email, $list_id ) ) {
						$provider_class->subscribe_user( $member, $list_id );
					}
					do_action( 
						'mc_hustle_after_' .$this->provider . '_subscribe_registered', 
						$member, 
						$list_id 
					);
				} 
			}
		}
	}

	/**
	 * A user subscribed to a membership.
	 *
	 * @since  1.1.2
	 * @param  mixed $event
	 * @param  mixed $subscription
	 */
	public function subscribe_members( $event, $subscription ) {
		try {
			$provider_details = $this->get_provider_with_details();
			if ( is_array( $provider_details ) ) {
				list( $provider_class, $lists ) = $provider_details;
				$mail_list_registered 			= $lists['registered']['value'];
				$mail_list_deactivated 			= $lists['deactivated']['value'];
				$mail_list_members 				= $lists['members']['value'];
				$member 						= $subscription->get_member();

				if ( $mail_list_members != $mail_list_registered ) {
					/** Verify if is subscribed to registered mail list and remove it. */
					if ( $provider_class->is_user_subscribed( $member->email, $mail_list_registered ) ) {
						$provider_class->unsubscribe_user( $member->email, $mail_list_registered );
					}
				}

				if ( $mail_list_members != $mail_list_deactivated ) {
					/** Verify if is subscribed to deactivated mail list and remove it. */
					if ( $provider_class->is_user_subscribed( $member->email, $mail_list_deactivated ) ) {
						$provider_class->unsubscribe_user( $member->email, $mail_list_deactivated );
					}
				}

				if ( $mail_list_members ) {
					if ( ! $provider_class->is_user_subscribed( $member->email, $mail_list_members ) ) {
						$provider_class->subscribe_user( $member, $mail_list_members );
					}
				}
				do_action( 
					'mc_hustle_after_' .$this->provider . '_subscribe_members', 
					$member, 
					$mail_list_registered, 
					$mail_list_deactivated, 
					$mail_list_members 
				);
			}
		} catch ( Exception $e ) {
			$this->log( $e->getMessage() );
		}
	}

	/**
	 * A membership was deactivated (e.g. expired or manually cancelled)
	 *
	 * @since  1.1.2
	 * @param  mixed $event
	 * @param  mixed $subscription
	 */
	public function subscribe_deactivated( $event, $subscription ) {
		try {
			$provider_details = $this->get_provider_with_details();
			if ( is_array( $provider_details ) ) {
				list( $provider_class, $lists ) = $provider_details;

				$mail_list_registered 			= $lists['registered']['value'];
				$mail_list_deactivated 			= $lists['deactivated']['value'];
				$mail_list_members 				= $lists['members']['value'];
				$member 						= $subscription->get_member();

				//Check if member has a new subscription
				$membership 					= $subscription->get_membership();
				$new_membership 				= MS_Factory::load(
					'MS_Model_Membership',
					$membership->on_end_membership_id
				);
				if ( !$new_membership->is_valid() ) {
					if ( $mail_list_deactivated == $mail_list_registered ) {
						/** Verify if is subscribed to registered mail list and remove it. */
						if ( $provider_class->is_user_subscribed( $member->email, $mail_list_registered ) ) {
							$provider_class->unsubscribe_user( $member->email, $mail_list_registered );
						}
					}
	
					if ( $mail_list_deactivated == $mail_list_members ) {
						/** Verify if is subscribed to deactivated mail list and remove it. */
						if ( $provider_class->is_user_subscribed( $member->email, $mail_list_members ) ) {
							$provider_class->unsubscribe_user( $member->email, $mail_list_members );
						}
					}
	
					if ( ! $provider_class->is_user_subscribed( $member->email, $mail_list_deactivated ) ) {
						$provider_class->unsubscribe_user( $member, $mail_list_deactivated );
					}

					do_action( 
						'mc_hustle_after_' .$this->provider . '_subscribe_deactivated', 
						$member, 
						$mail_list_registered, 
						$mail_list_deactivated, 
						$mail_list_members 
					);
				}
			}
		} catch ( Exception $e ) {
			$this->log( $e->getMessage() );
		}
	}
}
?>