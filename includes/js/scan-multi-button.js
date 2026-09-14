jQuery( $ => {
    const data = blnotifier_scan_multi_button;
    const currentURL = window.location.href;
    var btnURL;
    var btnText;

    if ( currentURL.includes( 'blinks=true' ) && currentURL.includes( '_wpnonce=' + data.nonce ) ) {
        btnURL = data.stop_url;
        btnText = 'Stop Scanning';
    } else {
        btnURL = data.start_url;
        btnText = 'Scan for Broken Links';
    }

    $( '.wrap > a.page-title-action' ).after( `<a id="bln-run-scan" href="${btnURL}" class="page-title-action" style="margin-left: 10px;"><span class="text">${btnText}</span><span class="done"></span></a>` );
} );