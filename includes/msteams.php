<?php 
/**
 * MS Teams integrations class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The class.
 */
class BLNOTIFIER_MSTEAMS {

    // $args = [
    //     'color'         => '#788F9B',
    //     'site_name'     => 'Your Site Name', // Defaults to blog name
    //     'title'         => 'Broken Links Found',
    //     'msg'           => 'The message',
    //     'img_url'       => '',
    //     'facts' => [
    //         [
    //             'name'   => 'Field Name',
    //             'value'  => 'Field Value',
    //         ]
    //     ],
    //     'buttons' => [
    //         [
    //             'title' => 'Button Name',
    //             'url'   => 'Button Value',
    //         ]
    //     ]
    // ];
    /**
     * Send a MS Teams message
     *
     * @param array $args
     * @param string $webhook
     * @return boolean
     */
    public function send( $webhook, $args ) {
        // Validate webhook
        $webhook = $this->sanitize_webhook_url( $webhook );
        if ( $webhook == '' ) {
            error_log( 'Could not send notification to MS Teams. Webhook URL ('.$webhook.') is not valid. URL should look like this: https://yourdomain.webhook.office.com/xxx/xxx...' );
            return false;
        }

        // Get the accent color
		$color = $this->sanitize_and_validate_color( $args[ 'color' ], '#788F9B' );

        // Title
        if ( isset( $args[ 'title' ] ) && sanitize_text_field( $args[ 'title' ] ) != '' ) {
            $title = sanitize_text_field( $args[ 'title' ] );
        } else {
            $title = esc_html__( 'Broken Links Found', 'broken-link-notifier' );
        }

        // Message
        if ( isset( $args[ 'msg'] ) && sanitize_textarea_field( $args[ 'msg' ] ) != '' ) {
            $message = ' - '.sanitize_textarea_field( $args[ 'msg' ] );
        } else {
            $message = '';
        }

        // Site name
        if ( isset( $args[ 'site_name' ] ) && sanitize_text_field( $args[ 'site_name' ] ) != '' ) {
            $site_name = sanitize_text_field( $args[ 'site_name' ] );
        } else {
            $site_name = get_bloginfo( 'name' );
        }

        // Image
        if ( isset( $args[ 'img_url' ] ) && filter_var( $args[ 'img_url' ], FILTER_SANITIZE_URL ) != '' ) {
            $image = filter_var( $args[ 'img_url' ], FILTER_SANITIZE_URL );
        } else {
            $image = BLNOTIFIER_PLUGIN_IMG_PATH.'logo-transparent.png';
        }

		// Facts
        if ( isset( $args[ 'facts' ] ) && !empty( $args[ 'facts' ] ) ) {
            $facts = filter_var_array( $args[ 'facts' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        } else {
            $facts = [];
        }

        // Put the message card together
        $data = [
            '@type'      => 'MessageCard',
            '@context'   => 'https://schema.org/extensions',
            'summary'    => 'Broken Link Notifier Microsoft Teams Integration',
            'themeColor' => $color,
            'title'      => $title,
            'sections'   => [
                [
                    'activityTitle'    => $site_name,
                    'activitySubtitle' => home_url(),
                    'activityImage'    => $image,
                    'text'             => (new BLNOTIFIER_HELPERS)->convert_timezone().$message,
                    'facts'            => $facts,
                ]
            ],
        ];

		// Visit Site Button
		$data[ 'potentialAction' ][] = [
            '@type'   => 'OpenUri',
            'name'    => esc_html__( 'Visit Site', 'broken-link-notifier' ),
            'targets' => [
                [
                    'os'  => 'default',
                    'uri' => home_url()
                ]
            ]
        ];

        // Go to page with broken links
        if ( isset( $args[ 'source_url' ] ) && filter_var( $args[ 'source_url' ], FILTER_SANITIZE_URL ) != '' ) {
            $data[ 'potentialAction' ][] = [
                '@type'   => 'OpenUri',
                'name'    => esc_html__( 'View Page', 'broken-link-notifier' ),
                'targets' => [
                    [
                        'os'  => 'default',
                        'uri' => add_query_arg( 'blinks', 'true', filter_var( $args[ 'source_url' ], FILTER_SANITIZE_URL) )
                    ]
                ]
            ];    
        }
		
		// Go to broken links page
		$data[ 'potentialAction' ][] = [
            '@type'   => 'OpenUri',
            'name'    => esc_html__( 'View Broken Links', 'broken-link-notifier' ),
            'targets' => [
                [
                    'os'  => 'default',
                    'uri' => (new BLNOTIFIER_MENU)->get_plugin_page( 'results' )
                ]
            ]
        ];

		// Custom Button
		if ( isset( $args[ 'buttons' ] ) && !empty( filter_var_array( $args[ 'buttons' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ) {

            // Iter
            foreach ( filter_var_array( $args[ 'buttons' ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) as $button ) {
                if ( isset( $button[ 'text' ] ) && sanitize_text_field( $button[ 'text' ] ) != '' && 
                     isset( $button[ 'url' ] ) && filter_var( $button[ 'url' ], FILTER_SANITIZE_URL ) != '' ) {

                    // The button
                    $data[ 'potentialAction' ][] = [
                        '@type'   => 'OpenUri',
                        'name'    => sanitize_text_field( $button[ 'text' ] ),
                        'targets' => [
                            [
                                'os'  => 'default',
                                'uri' => filter_var( $button[ 'url' ], FILTER_SANITIZE_URL )
                            ]
                        ]
                    ];
                }
            }
		}

        // Encode
        $json_data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        // Send it to discord
        $options = [
            'body'        => $json_data,
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
        ];
        $send = wp_remote_post( esc_url( $webhook ), $options );
        if ( !is_wp_error( $send ) && !empty( $send ) ) {
            if ( $send[ 'response' ][ 'code' ] != 400 ) {
                return $send[ 'response' ][ 'code' ].' - '.$send[ 'response' ][ 'message' ];
            } else {
                error_log( 'Could not send to MS Teams channel for the following reason: '.$send[ 'response' ][ 'code' ].' - '.$send[ 'response' ][ 'message' ].'. There is an error in your MS Teams args.' );
                return false;
            }
        } else {
            error_log( 'Could not send to MS Teams channel for the following reason: '.$send->get_error_message() );
            return false;
        }
    } // End send()

    
    /**
     * Sanitize the webhook url
     *
     * @param string $webhook
     * @return string
     */
    public function sanitize_webhook_url( $webhook ) {
        if ( !strpos( $webhook, 'webhook.office.com' ) ) {
            return '';
        } else {
            return filter_var( $webhook, FILTER_SANITIZE_URL );
        }
    } // sanitize_webhook_url()


    /**
	 * Sanitize a hex color and force hash
	 *
	 * @param string $color
	 * @param string $default
	 * @return string|void
	 */
	public function sanitize_and_validate_color( $color, $default ) {
		// Check if color exists and if it's still not blank after sanitation
		if ( $color && ( sanitize_hex_color( $color ) != '' || sanitize_hex_color_no_hash( $color ) != '' ) ) {
			
			// If it has hash
			if ( str_starts_with( $color, '#' ) ) {
				$color = sanitize_hex_color( $color );

			// If it does not have hash
			} else {
				$color = '#'.sanitize_hex_color_no_hash( $color );
			}

		// Otherwise return the sanitized default
		} else {
			$color = sanitize_hex_color( $default );
		}

		// Return the color
		return $color;
	} // End sanitize_and_validate_color()
}