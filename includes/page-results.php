<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'blnotifier_results';

if ( isset( $_POST[ 'bln_delete_selected' ] ) && isset( $_POST[ 'bln_delete_nonce' ] ) ) {
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'bln_delete_nonce' ] ) ), 'bln_delete_results' ) ) {
        wp_die( 'Security check failed' );
    }

    if ( ! empty( $_POST[ 'bln_selected' ] ) && is_array( $_POST[ 'bln_selected' ] ) ) {
        $ids = array_map( 'intval', $_POST[ 'bln_selected' ] );
        if ( ! empty( $ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($placeholders)",
                    $ids
                )
            );
        }
    }

    wp_redirect( remove_query_arg( [ '_wp_http_referer', '_wpnonce' ] ) );
    exit;
}

// Pagination settings
$per_page = absint( get_option( 'blnotifier_per_page', 50 ) );
if ( isset( $_GET[ 'blnotifier_per_page' ] ) ) {
    $new_per_page = max( 1, intval( $_GET[ 'blnotifier_per_page' ] ) );
    update_option( 'blnotifier_per_page', $new_per_page );
    $per_page = $new_per_page;
}

$current_page = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

// Total items
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

// Fetch paginated results
$links = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $table_name ORDER BY created_at ASC LIMIT %d OFFSET %d",
    $per_page,
    $offset
) );

// Total pages
$total_pages = ceil( $total_items / $per_page );

// Base URL for pagination
$base_url = remove_query_arg( 'paged' );

// URLs for first/prev/next/last
$first_url = add_query_arg( 'paged', 1, $base_url );
$last_url  = add_query_arg( 'paged', $total_pages, $base_url );
$prev_url  = $current_page > 1 ? add_query_arg( 'paged', $current_page - 1, $base_url ) : $first_url;
$next_url  = $current_page < $total_pages ? add_query_arg( 'paged', $current_page + 1, $base_url ) : $last_url;

// Determine disabled states
$first_disabled = $current_page <= 1;
$prev_disabled  = $current_page <= 1;
$next_disabled  = $current_page >= $total_pages;
$last_disabled  = $current_page >= $total_pages;

// Build pagination HTML once
$pagination_html = '';
if ( $total_pages > 1 ) {
    $pagination_html = '
    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="pagination-links">
                <a class="first-page button' . ( $first_disabled ? ' disabled' : '' ) . '"' . ( $first_disabled ? '' : ' href="' . esc_url( $first_url ) . '"' ) . '>
                    <span class="screen-reader-text">' . esc_html__( 'First page', 'broken-link-notifier' ) . '</span>
                    <span aria-hidden="true">«</span>
                </a>
                <a class="prev-page button' . ( $prev_disabled ? ' disabled' : '' ) . '"' . ( $prev_disabled ? '' : ' href="' . esc_url( $prev_url ) . '"' ) . '>
                    <span class="screen-reader-text">' . esc_html__( 'Previous page', 'broken-link-notifier' ) . '</span>
                    <span aria-hidden="true">‹</span>
                </a>
                <span class="paging-input">
                    <label for="current-page-selector" class="screen-reader-text">' . esc_html__( 'Current Page', 'broken-link-notifier' ) . '</label>
                    <input class="current-page" id="current-page-selector" type="text" name="paged" formmethod="get" value="' . esc_attr( $current_page ) . '" size="1" aria-describedby="table-paging">
                    <span class="tablenav-paging-text">' . esc_html__( 'of', 'broken-link-notifier' ) . ' <span class="total-pages">' . esc_html( $total_pages ) . '</span></span>
                </span>
                <a class="next-page button' . ( $next_disabled ? ' disabled' : '' ) . '"' . ( $next_disabled ? '' : ' href="' . esc_url( $next_url ) . '"' ) . '>
                    <span class="screen-reader-text">' . esc_html__( 'Next page', 'broken-link-notifier' ) . '</span>
                    <span aria-hidden="true">›</span>
                </a>
                <a class="last-page button' . ( $last_disabled ? ' disabled' : '' ) . '"' . ( $last_disabled ? '' : ' href="' . esc_url( $last_url ) . '"' ) . '>
                    <span class="screen-reader-text">' . esc_html__( 'Last page', 'broken-link-notifier' ) . '</span>
                    <span aria-hidden="true">»</span>
                </a>
            </span>
        </div>
    </div>';
}

$allowed_tags = [
    'div'    => [
        'class' => true,
    ],
    'span'   => [
        'class'       => true,
        'aria-hidden' => true,
    ],
    'a'      => [
        'class'  => true,
        'href'   => true,
        'target' => true,
    ],
    'input'  => [
        'type'              => true,
        'name'              => true,
        'id'                => true,
        'value'             => true,
        'size'              => true,
        'class'             => true,
        'aria-describedby'  => true,
    ],
    'label'  => [
        'for'   => true,
        'class' => true,
    ],
];
?>

<style>
/* Example minimal styling, adapt as needed */
.results th, .results td {
    padding: 8px 12px;
    text-align: left;
}
.bln-type {
    padding: 5px 10px;
    font-weight: bold;
    margin-bottom: 10px;
    width: 100px;
    text-align: center;
    text-transform: uppercase;
    box-shadow: 0 2px 4px 0 rgba(7, 36, 86, 0.075);
    border: 1px solid rgba(7, 36, 86, 0.075);
    border-radius: 10px;
}
.bln-type code {
    margin-right: 10px;
}
.bln-type.broken {
    background: red;
    color: white;
}
.bln-type.warning {
    background: yellow;
    color: black;
}
.bln-type.good,
.bln-type.fixed {
    background: green;
    color: white;
}
.link-url,
.source-url {
    font-weight: 600;
}
.bln_source strong {
    display: block;
    margin-bottom: 0.2em;
    font-size: 14px;
}
.bln_source .row-actions {
    padding-top: 2px;
}
#message {
    display: none;
}
tr.omitted {
    opacity: 0.5;
}

.blnotifier-notice {
    margin: 5px 0 30px;
    border-left-color: #72aee6 !important;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-left-width: 4px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    padding: 1px 12px;
}

.above-table-cont,
.below-table-cont {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 10px 0;
}

.above-table-cont .left-side {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.widefat.results .check-column:not(.link-row .check-column)  {
    padding: 0 !important;
    vertical-align: middle !important;
}
</style>

<div class="blnotifier-notice" >
    <p><?php echo esc_html__( 'This page shows the results of your scans. If enabled, the plugin automatically rechecks the links to see if they are still broken, but it does not remove the links from the pages or rescan the pages to see if broken links have been fixed. After fixing a broken link, you will need to clear the result below. Then when you rescan the page it should not show up here again. Note that the plugin will still find broken links if you simply hide them on the page.', 'broken-link-notifier' ); ?></p>
</div>

<form method="post">
    <?php wp_nonce_field( 'bln_delete_results', 'bln_delete_nonce' ); ?>

    <div class="above-table-cont">
        <div class="left-side">
            <button type="submit" id="bln-delete-selected" name="bln_delete_selected" class="button button-primary" disabled>
                <?php echo esc_html__( 'Delete Selected', 'broken-link-notifier' ); ?>
            </button>
            <div class="page-count">
                <strong><?php echo esc_html__( 'Total Links Found:', 'broken-link-notifier' ); ?></strong> <span id="bln-total-broken-links"><?php echo absint( $total_items ); ?></span>
            </div>
        </div>
        <div class="right-side">
            <?php echo wp_kses( $pagination_html, $allowed_tags ); ?>
        </div>
    </div>

    <table class="results wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>            
                <th id="cb" class="manage-column column-cb check-column">
                    <input id="cb-select-all-1" type="checkbox">
                    <label for="cb-select-all-1"><span class="screen-reader-text"><?php echo esc_html__( 'Select All', 'broken-link-notifier' ); ?></span></label>
                </th>
                <th class="type"><?php echo esc_html__( 'Type', 'broken-link-notifier' ); ?></th>
                <th class="link"><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                <th class="source"><?php echo esc_html__( 'Source', 'broken-link-notifier' ); ?></th>
                <th class="source_pt"><?php echo esc_html__( 'Source Post Type', 'broken-link-notifier' ); ?></th>
                <th class="date"><?php echo esc_html__( 'Date', 'broken-link-notifier' ); ?></th>
                <th class="verify"><?php echo esc_html__( 'Verify', 'broken-link-notifier' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $links as $link ) :

                $source_url = filter_var( $link->source, FILTER_SANITIZE_URL );
                $source_url = remove_query_arg( (new BLNOTIFIER_HELPERS)->get_qs_to_remove_from_source(), $source_url );
                $source_id = url_to_postid( $source_url );
                $post_type_name = $source_id ? (new BLNOTIFIER_HELPERS)->get_post_type_name( get_post_type( $source_id ), true ) : '--';

                // Type + code
                $type_label = '';
                switch ( $link->type ) {
                    case 'broken': $type_label = '<div class="bln-type broken">' . __( 'Broken', 'broken-link-notifier' ) . '</div>'; break;
                    case 'warning': $type_label = '<div class="bln-type warning">' . __( 'Warning', 'broken-link-notifier' ) . '</div>'; break;
                    case 'good': $type_label = '<div class="bln-type good">' . __( 'Good', 'broken-link-notifier' ) . '</div>'; break;
                }

                $code = absint( $link->code );
                $code_link = $code;
                $incl_title = '';

                if ( $code != 0 && $code != 666 ) {
                    $code_link = '<a href="https://http.dev/'.$code.'" target="_blank">'.$code.'</a>';
                } elseif ( $code == 666 ) {
                    $incl_title = ' title="' . __( 'A status code of 666 is a code we use to force invalid URL code 0 to be a broken link. It is not an official status code.', 'broken-link-notifier' ) . '"';
                } elseif ( $code == 0 ) {
                    $incl_title = ' title="' . __( 'A status code of 0 means there was no response and it can occur for various reasons, like request time outs. It almost always means something is randomly interfering with the user\'s connection, like a proxy server / firewall / load balancer / laggy connection / network congestion, etc.', 'broken-link-notifier' ) . '"';
                }

                // Author
                if ( isset( $link->guest ) && $link->guest ) {
                    $display_name = __( 'Guest', 'broken-link-notifier' );
                } elseif ( $link->author ) {
                    $user = get_user_by( 'ID', $link->author );
                    $display_name = $user ? $user->display_name : __( 'Guest', 'broken-link-notifier' );
                } else {
                    $display_name = __( 'Guest', 'broken-link-notifier' );
                }

                // Method
                switch ( sanitize_key( $link->method ) ) {
                    case 'visit': $method_label = __( 'Front-End Visit', 'broken-link-notifier' ); break;
                    case 'multi': $method_label = __( 'Multi-Scan', 'broken-link-notifier' ); break;
                    case 'single': $method_label = __( 'Page Scan', 'broken-link-notifier' ); break;
                    default: $method_label = __( 'Unknown', 'broken-link-notifier' ); 
                }

                // Actions for link
                $link_actions = [];
                $link_actions[] = '<span class="clear-result"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Clear Result', 'broken-link-notifier' ) . '</a></span>';
                $link_actions[] = '<span class="omit-link"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Omit Link', 'broken-link-notifier' ) . '</a></span>';
                $link_actions[] = '<span class="replace-link"><a href="#" data-link="'.esc_attr( $link->link ).'">' . __( 'Replace Link', 'broken-link-notifier' ) . '</a></span>';

                $source_title = get_the_title( $source_id );

                // Actions for source
                $source_actions = [];
                if ( $source_id ) {
                    $source_actions[] = '<span class="view"><a href="'.add_query_arg( 'blink', $link->link, get_permalink( $source_id ) ).'" target="_blank">' . __( 'View Page', 'broken-link-notifier' ) . '</a></span>';
                    if ( !(new BLNOTIFIER_OMITS)->is_omitted( $source_url, 'pages' ) ) {
                        $source_actions[] = '<span class="omit"><a class="omit-page" href="#" data-link="'.$source_url.'">' . __( 'Omit Page', 'broken-link-notifier' ) . '</a></span>';
                    }
                    $scan_nonce = wp_create_nonce( 'blnotifier_scan_single' );
                    $source_actions[] = '<span class="scan"><a class="scan-page" href="'.(new BLNOTIFIER_MENU)->get_plugin_page( 'scan-single' ).'&scan='.$source_url.'&_wpnonce='.$scan_nonce.'" target="_blank">' . __( 'Scan Page', 'broken-link-notifier' ) . '</a></span>';
                    $source_actions[] = '<span class="edit"><a href="'.get_edit_post_link( $source_id ).'">' . __( 'Edit Page', 'broken-link-notifier' ) . '</a></span>';
                    if ( get_option( 'blnotifier_enable_delete_source' ) && current_user_can( 'delete_post', $source_id ) ) {
                        $delete_nonce = wp_create_nonce( 'blnotifier_delete_source' );
                        $source_actions[] = '<span class="delete"><a href="#" class="delete-source" data-source-title="'.$source_title.'" data-source-id="'.$source_id.'">' . __( 'Trash Page', 'broken-link-notifier' ) . '</a></span>';
                    }
                }
                ?>
                <tr id="link-<?php echo esc_attr( $link->id ); ?>" class="link-row pending" data-link="<?php echo esc_attr( $link->link ); ?>" data-link-id="<?php echo esc_attr( $link->id ); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" id="cb-select-<?php echo esc_attr( $link->id ); ?>" class="bln-row-checkbox" name="bln_selected[]" value="<?php echo esc_attr( $link->id ); ?>" />
                    </th>
                    <td class="type"><?php echo wp_kses_post( $type_label ); ?> <code<?php echo wp_kses_post( $incl_title ); ?>><?php echo esc_html__( 'Code:', 'broken-link-notifier' ); ?> <?php echo wp_kses_post( $code_link ); ?></code> <span class="message"><?php echo esc_html( $link->text ); ?></span></td>
                    <td class="link">
                        <a href="<?php echo esc_url( $link->link ); ?>" class="link-url" target="_blank" rel="noopener"><?php echo esc_html( $link->link ); ?></a>
                        <div class="row-actions"><?php echo implode( ' | ', $link_actions ); ?></div>
                    </td>
                    <td class="source" data-source-id="<?php echo esc_attr( $source_id ); ?>">
                        <a href="<?php echo esc_url( $source_url ); ?>" class="source-url" target="_blank" rel="noopener"><?php echo esc_html( $source_id ? $source_title : $source_url ); ?></a>
                        <?php if ( $source_actions ) : ?>
                            <div class="row-actions"><?php echo implode( ' | ', $source_actions ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="source_pt"><?php echo esc_html( $post_type_name ); ?></td>
                    <td class="date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $link->date ) ) ); ?></td>
                    <td class="verify">
                        <?php
                        if ( !(new BLNOTIFIER_HELPERS())->is_results_verification_paused() ) {
                            echo '<span id="bln-verify-'.esc_attr( $link->id ).'" class="bln-verify" data-type="'.esc_attr( $link->type ).'" data-link="'.esc_html( $link->link ).'" data-link-id="'.esc_html( $link->id ).'" data-code="'.esc_attr( $link->code ).'" data-source-id="'.esc_attr( $source_id ).'" data-method="'.esc_attr( $link->method ).'">' . esc_html__( 'Pending', 'broken-link-notifier' ) . '</span>';
                        } else {
                            echo esc_html__( 'Auto-verification is paused in settings.', 'broken-link-notifier' );
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th id="cb" class="manage-column column-cb check-column">
                    <input id="cb-select-all-2" type="checkbox">
                    <label for="cb-select-all-2"><span class="screen-reader-text"><?php echo esc_html__( 'Select All', 'broken-link-notifier' ); ?></span></label>
                </th>
                <th class="type"><?php echo esc_html__( 'Type', 'broken-link-notifier' ); ?></th>
                <th class="link"><?php echo esc_html__( 'Link', 'broken-link-notifier' ); ?></th>
                <th class="source"><?php echo esc_html__( 'Source', 'broken-link-notifier' ); ?></th>
                <th class="source_pt"><?php echo esc_html__( 'Source Post Type', 'broken-link-notifier' ); ?></th>
                <th class="date"><?php echo esc_html__( 'Date', 'broken-link-notifier' ); ?></th>
                <th class="verify"><?php echo esc_html__( 'Verify', 'broken-link-notifier' ); ?></th>
            </tr>
        </tfoot>
    </table>
</form>

<div class="below-table-cont">
    <div class="left-side">
        <form method="get" style="display:inline;" id="bln-per-page-form">
            <label for="bln-per-page"><?php echo esc_html__( 'Show', 'broken-link-notifier' ); ?></label>
            <input type="number" id="bln-per-page" name="blnotifier_per_page" value="<?php echo esc_attr( $per_page ); ?>" min="1" style="width: 60px;">
            <input type="hidden" name="page" value="broken-link-notifier">
            <input type="hidden" name="tab" value="results">
            <button type="submit" class="button"><?php echo esc_html__( 'Apply', 'broken-link-notifier' ); ?></button>
        </form>
    </div>
    <div class="right-side">
        <?php echo wp_kses( $pagination_html, $allowed_tags ); ?>
    </div>
</div>