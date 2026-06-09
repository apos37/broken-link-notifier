<?php
/**
 * Slack integrations class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * The class.
 */
class BLNOTIFIER_SLACK {

    /**
     * Webhook prefix
     *
     * @var string
     */
    public $webhook_prefix = 'https://hooks.slack.com/services/';


    // $args = [
    //     'title'   => 'Broken Links Found',
    //     'source'  => 'https://example.com/page',
    //     'fields'  => [
    //         [
    //             'link' => 'https://example.com/broken',
    //             'code' => 404,
    //             'text' => 'Not Found',
    //         ]
    //     ]
    // ];
    /**
     * Send a message to Slack
     *
     * @param string $webhook
     * @param array  $args
     * @return string|boolean
     */
    public function send( $webhook, $args ) {
        $webhook = $this->sanitize_webhook_url( $webhook );
        if ( $webhook == '' ) {
            error_log( 'Could not send notification to Slack. Webhook URL ('.$webhook.') is not valid. URL should look like this: https://hooks.slack.com/services/xxx/xxx/xxx' ); // phpcs:ignore
            return false;
        }

        $title = isset( $args[ 'title' ] ) ? sanitize_text_field( $args[ 'title' ] ) : __( 'Broken Links Found', 'broken-link-notifier' );
        $source = isset( $args[ 'source' ] ) ? esc_url( $args[ 'source' ] ) : '';

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type'  => 'plain_text',
                    'text'  => $title,
                    'emoji' => true,
                ],
            ],
        ];

        if ( $source ) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Source:* <'.$source.'|'.$source.'>',
                ],
            ];
        }

        if ( !empty( $args[ 'fields' ] ) ) {
            foreach ( $args[ 'fields' ] as $field ) {
                $link = esc_url( $field[ 'link' ] );
                $code = absint( $field[ 'code' ] );
                $text = sanitize_text_field( $field[ 'text' ] );
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*Broken Link:* <'.$link.'|'.$link.'>'."\n".'*Status Code:* '.$code.' - '.$text,
                    ],
                ];
            }
        }

        $blocks[] = [
            'type' => 'divider',
        ];

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => get_bloginfo( 'name' ).' | '.BLNOTIFIER_NAME,
                ],
            ],
        ];

        $data = [ 'blocks' => $blocks ];

        $json_data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

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
                error_log( 'Could not send to Slack channel for the following reason: '.$send[ 'response' ][ 'code' ].' - '.$send[ 'response' ][ 'message' ].'. There is an error in your Slack args.' ); // phpcs:ignore
                return false;
            }
        } else {
            error_log( 'Could not send to Slack channel for the following reason: '.$send->get_error_message() ); // phpcs:ignore
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
    } // End sanitize_webhook_url()

}