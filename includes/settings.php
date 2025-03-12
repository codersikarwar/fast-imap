<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}
global $wpdb;
$table = $wpdb->prefix . 'fast_imap';

// Fetch all stored credentials
$credentials = $wpdb->get_results("SELECT * FROM $table");

?>

<div class="wrap">
    <h2>Add New Account</h2>

    <form method="post">
        <input type="hidden" name="fast_imap_credentials_form" value="1">
        <input type="hidden" name="fast_imap_nonce" value="<?php echo wp_create_nonce('fast_imap_action'); ?>">

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="imap_host">IMAP Host:</label></th>
                <td><input type="text" name="imap_host" id="imap_host" class="regular-text" required></td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="imap_port">IMAP Port:</label></th>
                <td><input type="number" name="imap_port" id="imap_port" value="993" class="regular-text" required></td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="imap_username">Username:</label></th>
                <td><input type="text" name="imap_username" id="imap_username" class="regular-text" required></td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="imap_password">Password:</label></th>
                <td><input type="password" name="imap_password" id="imap_password" class="regular-text" required></td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="encryption">Encryption:</label></th>
                <td>
                    <select name="encryption" id="encryption">
                        <option value="ssl">SSL</option>
                        <option value="tls">TLS</option>
                        <option value="none">None</option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button('Save'); ?>
    </form>

    <h2>All Accounts</h2>

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>IMAP Host</th>
                <th>IMAP Port</th>
                <th>Username</th>
                <th>Encryption</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($credentials): ?>
                <?php foreach ($credentials as $cred): ?>
                    <tr>
                        <td><?php echo esc_html($cred->id); ?></td>
                        <td><?php echo esc_html($cred->imap_host); ?></td>
                        <td><?php echo esc_html($cred->imap_port); ?></td>
                        <td><?php echo esc_html($cred->imap_username); ?></td>
                        <td><?php echo esc_html($cred->encryption); ?></td>
                        <td>
                        <a href="?page=fast-imap-settings&delete_id=<?php echo $cred->id; ?>&fast_imap_nonce=<?php echo wp_create_nonce('fast_imap_delete_action_' . $cred->id); ?>"
                        onclick="return confirm('Are you sure?');" class="button">
                         <?php esc_html_e('Delete', 'fast-imap-settings'); ?>
                        </a>
                       </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No Accounts found. Try adding one.', 'fast-imap-settings'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>