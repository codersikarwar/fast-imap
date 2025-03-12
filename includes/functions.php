<?php


// Plugin Activation - Create Table
function fast_imap_activate() {
    global $wpdb;
 
 
 
    $table = $wpdb->prefix . 'fast_imap';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        imap_host VARCHAR(255) NOT NULL,
        imap_port INT NOT NULL DEFAULT 993,
        imap_username VARCHAR(255) NOT NULL UNIQUE,
        imap_password TEXT NOT NULL,
        encryption ENUM('ssl', 'tls', 'none') NOT NULL DEFAULT 'ssl',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    if ( ! defined( 'FAST_IMAP_ENCRYPTION_KEY' ) || FAST_IMAP_ENCRYPTION_KEY === '' ) {
        $key = bin2hex( random_bytes( 32 ) ); // 256-bit key
        define( 'FAST_IMAP_ENCRYPTION_KEY', $key );
        update_option( 'fast_imap_encryption_key', $key );
    }
}



// Plugin Deactivation - Drop Table
function fast_imap_deactivate() {
    global $wpdb;
    $table = $wpdb->prefix . 'fast_imap';
    $sql = "DROP TABLE IF EXISTS {$table};";
    $wpdb->query( $sql );
    delete_option( 'fast_imap_encryption_key' );
}



function fast_imap_encrypt_password( $password ) {
    $key = FAST_IMAP_ENCRYPTION_KEY;

    if ( empty( $key ) ) {
        return false; // Key not set
    }

    $ivlen = openssl_cipher_iv_length( 'AES-256-CBC' );
    $iv = openssl_random_pseudo_bytes( $ivlen );
    $ciphertext_raw = openssl_encrypt( $password, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    $hmac = hash_hmac( 'sha256', $ciphertext_raw, $key, true );
    return base64_encode( $iv . $hmac . $ciphertext_raw );
}

function fast_imap_decrypt_password( $ciphertext ) {
    $key = FAST_IMAP_ENCRYPTION_KEY;

    if ( empty( $key ) ) {
        return false; // Key not set
    }

    $c = base64_decode( $ciphertext );
    $ivlen = openssl_cipher_iv_length( 'AES-256-CBC' );
    $iv = substr( $c, 0, $ivlen );
    $hmac = substr( $c, $ivlen, 32 );
    $ciphertext_raw = substr( $c, $ivlen + 32 );
    $original_plaintext = openssl_decrypt( $ciphertext_raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    $calcmac = hash_hmac( 'sha256', $ciphertext_raw, $key, true );
    if ( hash_equals( $hmac, $calcmac ) ) {
        return $original_plaintext;
    }
    return false;
}

// Admin Menu
function fast_imap_menu() {
    add_menu_page(
        __( 'Fast IMAP', 'fast-imap' ), 
        __( 'Fast IMAP', 'fast-imap' ),
        'manage_options',
        'fast-imap',
        'fast_imap_inbox',
        'dashicons-email',
        25
    );
    add_submenu_page(
        'fast-imap',
        __( 'Fast IMAP - Inbox', 'fast-imap' ),
        __( 'Inbox', 'fast-imap' ),
        'manage_options',
        'fast-imap',
        'fast_imap_inbox'
    );
    add_submenu_page(
        'fast-imap',
        __( 'Fast IMAP - Settings', 'fast-imap' ),
        __( 'Settings', 'fast-imap' ),
        'manage_options',
        'fast-imap-settings',
        'fast_imap_settings'
    );
}

function fast_imap_inbox() {
    include plugin_dir_path( __FILE__ ) . 'inbox.php';
}

function fast_imap_settings() {
    include plugin_dir_path( __FILE__ ) . 'settings.php';
}

function fast_imap_handle_credentials_submission() {
    global $wpdb;
    $table = $wpdb->prefix . 'fast_imap';

    if ( isset( $_POST['fast_imap_credentials_form'] ) ) {
        // Verify nonce
        if ( ! isset( $_POST['fast_imap_nonce'] ) || ! wp_verify_nonce( $_POST['fast_imap_nonce'], 'fast_imap_action' ) ) {
            wp_die( __( 'Cheating\' huh?', 'fast-imap' ) ); 
        }

        // Sanitize input
        $imap_host     = sanitize_text_field( $_POST['imap_host'] );
        $imap_port     = intval( $_POST['imap_port'] );
        $imap_username = sanitize_text_field( $_POST['imap_username'] );
        $imap_password = fast_imap_encrypt_password($_POST['imap_password']); 
        $encryption    = sanitize_text_field( $_POST['encryption'] );

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( ! $table_exists ) {
            error_log( "Error: Table '{$table}' does not exist." );
            return;
        }

        // Insert into database
        $result = $wpdb->insert(
            $table,
            [
                'imap_host'     => $imap_host,
                'imap_port'     => $imap_port,
                'imap_username' => $imap_username,
                'imap_password' => $imap_password,
                'encryption'    => $encryption,
            ],
            [ '%s', '%d', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            // Handle the database error
            error_log( "Database error: " . $wpdb->last_error );
            
            echo '<div class="notice notice-error is-dismissible"><p>There was an error while adding account!</p></div>';
            
        }else{
              echo '<div class="notice notice-success is-dismissible"><p>Account Added successfully!</p></div>';
        }
        
    }
}

function fast_imap_handle_deletion() {
    if ( isset( $_GET['delete_id'] ) ) {
        $delete_id = intval( $_GET['delete_id'] );

        if ( isset( $_GET['fast_imap_nonce'] ) && wp_verify_nonce( $_GET['fast_imap_nonce'], 'fast_imap_delete_action_' . $delete_id ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'fast_imap';

            $wpdb->delete(
                $table,
                array( 'id' => $delete_id ),
                array( '%d' )
            );

            if ( $wpdb->rows_affected > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>Account deleted successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Failed to delete Account.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Invalid nonce, deletion failed.</p></div>';
        }
    }
}

 
 function fast_imap_connect_by_id( $account_id, $folder = 'inbox' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'fast_imap';

    $account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $account_id ) );
    if ( ! $account ) {
        return false; // Account not found
    }

    $imap_host     = $account->imap_host;
    $imap_port     = $account->imap_port;
    $imap_username = $account->imap_username;
    $imap_password = fast_imap_decrypt_password( $account->imap_password );
    $encryption    = $account->encryption;

    if ( empty( $imap_host ) || empty( $imap_username ) || empty( $imap_password ) ) {
        return false; // Missing credentials
    }

    $connection_string = '{' . $imap_host . ':' . $imap_port . '/' . $encryption . '/novalidate-cert}' . imap_utf7_encode($folder);

    try {
        $imap_stream = imap_open( $connection_string, $imap_username, $imap_password );
        if ( ! $imap_stream ) {
            error_log( "IMAP connection failed: " . imap_last_error() ); 
            return false;
        }
        return $imap_stream;
    } catch ( Exception $e ) {
        error_log( "IMAP connection exception: " . $e->getMessage() ); 
        return false; // Connection error
    }
    
}

/**
 * Gets the total number of messages in the selected mailbox.
 *
 * @param resource $imap_stream The IMAP stream.
 * @return int|false The total number of messages or false on failure.
 */
function fast_imap_get_total_messages( $imap_stream ) {
    try {
        imap_reopen($imap_stream, imap_utf7_decode(imap_mailboxmsginfo($imap_stream)->Mailbox));
        return imap_num_msg( $imap_stream );
    } catch ( Exception $e ) {
        return false;
    }
}

function fast_imap_fetch_email_summaries( $imap_stream, $start, $limit ) {
    try {
        imap_reopen($imap_stream, imap_utf7_decode(imap_mailboxmsginfo($imap_stream)->Mailbox));
        $total_messages = imap_num_msg( $imap_stream );
        if ( $total_messages === 0 ) {
            return []; // No messages
        }

        $end = min( $start + $limit - 1, $total_messages - 1 ); // Adjust for 0-based indexing
        $range = ($start + 1) . ':' . ($end + 1); // IMAP message numbers are 1-based

        $overview = imap_fetch_overview( $imap_stream, $range );

        if ( ! $overview ) {
            return false;
        }

        $emails = [];
        foreach ( $overview as $mail ) {
            $emails[] = [
                'uid'     => $mail->uid,
                'from'    => $mail->from,
                'subject' => imap_utf8( $mail->subject ),
                'date'    => $mail->date,
            ];
        }
        return $emails;
    } catch ( Exception $e ) {
        return false;
    }
}

/**
 * Fetches the full content of a single email.
 *
 * @param resource $imap_stream The IMAP stream.
 * @param int      $uid         The UID of the email to fetch.
 * @return array|false An array containing the email content or false on failure.
 */
function fast_imap_fetch_email_content( $imap_stream, $uid ) {
    try {
        $message_number = imap_msgno( $imap_stream, $uid );
        if ( $message_number <= 0 ) {
            return false; // Invalid message number
        }

        $header = imap_headerinfo( $imap_stream, $message_number );

        // Try to get the HTML part
        $html_body = imap_fetchbody( $imap_stream, $message_number, '1.2' ); // Try part 1.2 (common for HTML)

        // If HTML part is empty, try to get the first part.
        if ( empty( $html_body ) ) {
            $html_body = imap_fetchbody( $imap_stream, $message_number, '1' ); // Try part 1 (often contains html or plain text)
        }

        if ( ! $header || empty( $html_body ) ) {
            return false;
        }

        $email = [
            'from'    => $header->from[0]->mailbox . '@' . $header->from[0]->host,
            'subject' => imap_utf8( $header->subject ),
            'date'    => date( 'Y-m-d H:i:s', $header->udate ),
            'body'    => $html_body,
        ];

        return $email;
    } catch ( Exception $e ) {
        return false;
    }
}

/**
 * Closes the IMAP connection.
 *
 * @param resource $imap_stream The IMAP stream.
 */
function fast_imap_close( $imap_stream ) {
    if ( $imap_stream ) {
        imap_close( $imap_stream );
    }
}
