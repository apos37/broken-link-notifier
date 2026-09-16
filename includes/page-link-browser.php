<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Initiate
$HELPERS = new BLNOTIFIER_HELPERS;
$LINK_BROWSER = new BLNOTIFIER_LINK_BROWSER;
$counts = $LINK_BROWSER->get_counts();
?>

<div class="blnotifier-box">
    <div class="blnotifier-box-body">

        <?php $LINK_BROWSER->render_status_counts( $counts, 'all' ); ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="bln-link-browser-kind" class="screen-reader-text">Kind</label>
                <select id="bln-link-browser-kind">
                    <option value="all">All Kinds</option>
                    <option value="link">Links Only</option>
                    <option value="image">Images Only</option>
                    <option value="file">Files Only</option>
                </select>
            </div>
            <div class="alignleft actions">
                <input type="search" id="bln-link-browser-search" placeholder="Search links...">
            </div>
            <div class="alignleft actions">
                <label for="bln-link-browser-per-page" class="screen-reader-text">Per page</label>
                <select id="bln-link-browser-per-page">
                    <option value="10">10 per page</option>
                    <option value="25" selected>25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
            <div class="tablenav-pages bln-link-browser-pagination"></div>
            <br class="clear">
        </div>

        <table class="link-browser wp-list-table widefat fixed striped table-view-list" id="bln-link-browser-table">
            <thead>
                <tr>
                    <th class="link">Link</th>
                    <th class="page-count" style="width: 90px;"># of Pages</th>
                    <th class="pages">Pages Found On</th>
                    <th class="type" style="width: 120px;">Type</th>
                    <th class="actions" style="width: 220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="5"><em>Run a scan or adjust your filters to see results.</em></td></tr>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages bln-link-browser-pagination"></div>
            <br class="clear">
        </div>

    </div>
</div>