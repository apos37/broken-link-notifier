<?php 
/**
 * Discord integrations class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The class.
 */
class BLNOTIFIER_DISCORD {

    /**
     * Webhook prefix
     *
     * @var string
     */
    public $webhook_prefix = 'https://discord.com/api/webhooks/';
    

    // $args = [
    //     'msg'           => 'This is a test',
    //     'embed'         => true,
    //     'author_name'   => 'Apos37',
    //     'author_url'    => BLNOTIFIER_AUTHOR_URL,
    //     'title'         => 'My title',
    //     'title_url'     => 'https://mytitleurl.com',
    //     'desc'          => 'The description',
    //     'img_url'       => '',
    //     'thumbnail_url' => '',
    //     'disable_footer' => true,
    //     'fields' => [
    //          [
    //              'name'   => 'Field #2 Name',
    //              'value'  => 'Field #2 Value',
    //              'inline' => true
    //          ]
    //      ]
    // ];
    /**
     * Send a message to Discord
     * https://discord.com/developers/docs/resources/channel
     *
     * @param array $args
     * @param string $webhook
     * @return boolean
     */
    public function send( $webhook, $args ) {
        // Validate webhook
        $webhook = $this->sanitize_webhook_url( $webhook );
        if ( $webhook == '' ) {
            error_log( 'Could not send notification to Discord. Webhook URL ('.$webhook.') is not valid. URL should look like this: https://discord.com/api/webhooks/xxx/xxx...' ); // phpcs:ignore 
            return false;
        }

        // Timestamp
        $timestamp = gmdate( 'c', strtotime( 'now' ) );

        // Message data
        $data = [
            // Text-to-speech
            'tts' => false,
        ];

        // Message
        if ( isset( $args[ 'msg'] ) && sanitize_textarea_field( $args[ 'msg' ] ) != '' ) {
            $msg = sanitize_textarea_field( $args[ 'msg' ] );
            $msg = preg_replace( '/\{\{@([0-9]*?)\}\}/', '<@$1>', $msg );
            $msg = preg_replace( '/\{\{@\&([0-9]*?)\}\}/', '<@&$1>', $msg );
            $msg = preg_replace( '/\{\{#([0-9]*?)\}\}/', '<#$1>', $msg );
            $data[ 'content' ] = $msg;
        } else {
            $data[ 'content' ] = '';
        }

        // Change name of bot; default is DevDebugTools
        if ( isset( $args[ 'bot_name'] ) && sanitize_text_field( $args[ 'bot_name'] ) != '' ) {
            $data[ 'username' ] = sanitize_text_field( $args[ 'bot_name'] );
        }

        // Change bot avatar url
        if ( isset( $args[ 'bot_avatar_url'] ) && filter_var( $args[ 'bot_avatar_url' ], FILTER_SANITIZE_URL ) != '' ) {
            $data[ 'avatar_url' ] = filter_var( $args[ 'bot_avatar_url' ], FILTER_SANITIZE_URL );
        }

        // Embed
        if ( isset( $args[ 'embed' ] ) && filter_var( $args[ 'embed' ], FILTER_VALIDATE_BOOLEAN ) == true ) {
            $data[ 'embeds' ] = [
                [
                    // Embed Type
                    'type' => 'rich',

                    // Embed left border color in HEX
                    'color' => hexdec( '788F9B' ),

                    // Fields
                    'fields' => $args[ 'fields' ],
                ]
            ];

            // Are we adding the footer?
            if ( !isset( $args[ 'disable_footer' ] ) || filter_var( $args[ 'disable_footer' ], FILTER_VALIDATE_BOOLEAN ) !== true ) {
                // Footer
                $data[ 'embeds' ][0][ 'footer' ] = [
                    'text'     => BLNOTIFIER_NAME,
                    'icon_url' => BLNOTIFIER_PLUGIN_IMG_PATH.'logo-transparent.png'
                ];
                $data[ 'embeds' ][0][ 'timestamp' ] = $timestamp;
            }

            // Embed author
            if ( isset( $args[ 'author_name' ] ) && sanitize_text_field( $args[ 'author_name' ] ) != '' && 
                 isset( $args[ 'author_url' ] ) && filter_var( $args[ 'author_url' ], FILTER_SANITIZE_URL ) != '' &&
                 filter_var( $args[ 'author_url' ], FILTER_SANITIZE_URL ) != '#' ) {
                $data[ 'embeds' ][0][ 'author' ][ 'name' ] = sanitize_text_field( $args[ 'author_name' ] );
                $data[ 'embeds' ][0][ 'author' ][ 'url' ] = filter_var( $args[ 'author_url' ], FILTER_SANITIZE_URL );
            } else {
                $data[ 'embeds' ][0][ 'author' ][ 'name' ] = get_bloginfo( 'name' );
                $data[ 'embeds' ][0][ 'author' ][ 'url' ] = home_url();
            }

            // Embed title
            if ( isset( $args[ 'title' ] ) && sanitize_text_field( $args[ 'title' ] ) != '' ) {
                $data[ 'embeds' ][0][ 'title' ] = sanitize_text_field( $args[ 'title' ] );
            } else {
                $data[ 'embeds' ][0][ 'title' ] = 'Broken Links Found';
            }

            // Embed title link
            if ( isset( $args[ 'title_url' ] ) && filter_var( $args[ 'title_url' ], FILTER_SANITIZE_URL ) != '' ) {
                $data[ 'embeds' ][0][ 'url' ] = filter_var( $args[ 'title_url' ], FILTER_SANITIZE_URL );
            }

            // Embed description
            if ( isset( $args[ 'desc' ] ) && sanitize_textarea_field( $args[ 'desc' ] ) != '' ) {
                $desc = sanitize_textarea_field( $args[ 'desc' ] );
                $desc = preg_replace( '/\{\{@([0-9]*?)\}\}/', '<@$1>', $desc );
                $desc = preg_replace( '/\{\{@\&([0-9]*?)\}\}/', '<@&$1>', $desc );
                $desc = preg_replace( '/\{\{#([0-9]*?)\}\}/', '<#$1>', $desc );
                $data[ 'embeds' ][0][ 'description' ] = $desc;
            }

            // Embed attached image
            if ( isset( $args[ 'img_url' ] ) && filter_var( $args[ 'img_url' ], FILTER_SANITIZE_URL ) != '' ) {
                $data[ 'embeds' ][0][ 'image' ][ 'url' ] = filter_var( $args[ 'img_url' ], FILTER_SANITIZE_URL );
            }

            // Embed thumbnail
            if ( isset( $args[ 'thumbnail_url' ] ) && filter_var( $args[ 'thumbnail_url' ], FILTER_SANITIZE_URL ) != '' ) {
                $data[ 'embeds' ][0][ 'thumbnail' ][ 'url' ] = filter_var( $args[ 'thumbnail_url' ], FILTER_SANITIZE_URL );
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
                error_log( 'Could not send to Discord channel for the following reason: '.$send[ 'response' ][ 'code' ].' - '.$send[ 'response' ][ 'message' ].'. There is an error in your Discord args.' ); // phpcs:ignore 
                return false;
            }
        } else {
            error_log( 'Could not send to Discord channel for the following reason: '.$send->get_error_message() ); // phpcs:ignore 
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
        if ( !str_starts_with( $webhook, $this->webhook_prefix ) ) {
            return '';
        } else {
            return filter_var( $webhook, FILTER_SANITIZE_URL );
        }
    } // sanitize_webhook_url()
}