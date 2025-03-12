<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

global $wpdb;
$table = $wpdb->prefix . 'fast_imap';

// Check if any IMAP accounts exist
$account_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
if ( ! $account_exists ) {
    echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please add at least one IMAP account in the settings.', 'fast-imap' ) . '</p></div>';
    return; // Exit if no accounts
}

// Get IMAP accounts
$imap_accounts = $wpdb->get_results( "SELECT id, imap_username FROM {$table}" );

// Get selected account and folder
$selected_account_id = isset( $_GET['account'] ) ? absint( $_GET['account'] ) : ( ! empty( $imap_accounts ) ? $imap_accounts[0]->id : 0 );
$selected_folder  = 'INBOX';

// --- Pagination ---
$page       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page   = 5; // Number of emails to display per page
$offset     = ( $page - 1 ) * $per_page;

// --- IMAP Connection ---
$imap_stream = null;
$account = null;
if ( $selected_account_id ) {
    $imap_stream = fast_imap_connect_by_id( $selected_account_id, $selected_folder );
    if ( ! $imap_stream ) {
        // Handle connection error (display a message and exit)
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not connect to IMAP server.', 'fast-imap' ) . '</p></div>';
    }
    
}

// --- Fetch Email Summaries ---
$email_summaries = [];
if ( $imap_stream ) {
    $email_summaries = fast_imap_fetch_email_summaries( $imap_stream, $offset, $per_page );
}


?>
<div class="wrap">

    <?php 
    if (isset($_GET['action']) && $_GET['action'] === 'view_email' && isset($_GET['uid']) && $imap_stream) {
        $uid = absint($_GET['uid']);
        $email_content = fast_imap_fetch_email_content($imap_stream, $uid);

        if ($email_content) {
            echo '<h3>' . esc_html($email_content['subject']) . '</h3>';
            echo '<p><strong>From:</strong> ' . esc_html($email_content['from']) . '</p>';
            echo '<p><strong>Date:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($email_content['date']))) . '</p>';
            echo '<div class="email-content">' . wp_kses_post($email_content['body']) . '</div>'; // Use wp_kses_post for safety
        } else {
            echo '<p>' . esc_html__('Could not retrieve email content.', 'fast-imap') . '</p>';
        }
    } else {  ?>
        
    
    <h2><?php esc_html_e( 'Inbox', 'fast-imap' ); ?></h2>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">
        
        <select name="account" id="account">
            <?php foreach ( $imap_accounts as $account ): ?>
                <option value="<?php echo esc_attr( $account->id ); ?>" <?php selected( $selected_account_id, $account->id ); ?>>
                    <?php echo esc_html( $account->imap_username ); ?>
                </option>
            <?php endforeach; ?>
        </select>


        <button type="submit" class="button"><?php esc_html_e( 'Select', 'fast-imap' ); ?></button>
    </form>
    
   <?php if ( $email_summaries ) { ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'From', 'fast-imap' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'fast-imap' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'fast-imap' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'fast-imap' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $email_summaries as $email ): ?>
                    <tr>
                        <td><?php echo esc_html( $email['from'] ); ?></td>
                        <td><?php echo esc_html( $email['subject'] ); ?></td>
                        <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $email['date'] ) ) ); ?></td>
                        <td>
                            <a href="?page=fast-imap&action=view_email&uid=<?php echo intval( $email['uid'] ); ?>" class="button"><?php esc_html_e( 'View', 'fast-imap' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // --- Pagination Links ---
        $total_emails = fast_imap_get_total_messages($imap_stream);
        $num_pages    = ceil( $total_emails / $per_page );

        echo '<div class="tablenav">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(
            array(
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'prev_text' => __( '&laquo;', 'fast-imap' ),
                'next_text' => __( '&raquo;', 'fast-imap' ),
                'total'     => $num_pages,
                'current'   => $page,
            )
        );
        echo '</div>';
        echo '</div>';
        
        } else {
            echo '<p>' . esc_html__('No emails found.', 'fast-imap') . '</p>';
        }
    }
        ?>

</div>

<?php
// --- Close IMAP Connection ---
if ( $imap_stream ) {
    fast_imap_close( $imap_stream );
}
?>