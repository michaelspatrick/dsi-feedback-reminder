<?php
/*
Plugin Name: DSI Seminar Feedback Reminder
Description: Sends seminar attendees an email requesting feedback one hour after the seminar ends.
Version: 1.1
Author: Michael Patrick
*/

// Schedule cron event on plugin activation
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('dsi_feedback_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'dsi_feedback_cron_hook');
    }
});

// Clear cron on deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('dsi_feedback_cron_hook');
});

// Cron task
add_action('dsi_feedback_cron_hook', 'dsi_send_feedback_reminders');

function dsi_send_feedback_reminders() {
    $args = [
        'post_type'   => 'event',
        'post_status' => 'publish',
        'meta_query'  => [
            [
                'key'     => 'event_end_date',
                'value'   => date('Y-m-d'),
                'compare' => '<=',
                'type'    => 'DATE'
            ],
            [
                'key'     => 'event_end_time',
                'value'   => date('H:i', strtotime('-1 hour')),
                'compare' => '<=',
                'type'    => 'TIME'
            ]
        ]
    ];

    $query = new WP_Query($args);
    if (!$query->have_posts()) return;

    $from_email = get_option('dsi_feedback_from_email', 'newsletter@dragonsociety.com');
    $subject    = get_option('dsi_feedback_subject', "We'd love your feedback on the seminar!");
    $body_raw   = get_option('dsi_feedback_message', "<p>Thank you for attending our seminar. We'd love your feedback.</p><p><a href='https://feedback.dragonsociety.com'>Click here</a> to share your thoughts.</p><p>Check out our <a href='https://www.patreon.com/c/DragonSocietyInternational'>Patreon</a> for exclusive content.</p>");

    foreach ($query->posts as $event) {
        $event_id = $event->ID;
        $product_ids = dsi_get_event_product_ids($event_id);
        if (empty($product_ids)) continue;

        $orders = wc_get_orders([
            'limit'        => -1,
            'status'       => ['completed', 'processing', 'pending'],
            'date_created' => '<=' . current_time('mysql'),
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if (!in_array($item->get_product_id(), $product_ids)) continue;

                $meta_key = '_dsi_feedback_sent_' . $event_id;
                if ($order->get_meta($meta_key)) continue;

                $email = $order->get_billing_email();
                $name = $order->get_billing_first_name();
                $body = str_replace('{name}', esc_html($name), $body_raw);

                wp_mail($email, $subject, $body, [
                    'Content-Type: text/html; charset=UTF-8',
                    "From: $from_email"
                ]);

                $order->update_meta_data($meta_key, 1);
                $order->save();
            }
        }

        wp_mail("toritejutsu@gmail.com", $subject, $body_raw, [
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_email"
        ]);
    }
}

function dsi_get_event_product_ids($event_id) {
    $meta = get_post_meta($event_id, 'event_tickets', true);
    if (!is_array($meta)) return [];

    $ids = [];
    foreach ($meta as $ticket) {
        if (isset($ticket['woocommerce-product'])) {
            $ids[] = intval($ticket['woocommerce-product']);
        }
    }
    return $ids;
}

// Admin settings page
add_action('admin_menu', function() {
    add_options_page(
        'DSI Feedback Settings',
        'DSI Feedback Settings',
        'manage_options',
        'dsi-feedback-settings',
        'dsi_feedback_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('dsi_feedback_options', 'dsi_feedback_from_email');
    register_setting('dsi_feedback_options', 'dsi_feedback_subject');
    register_setting('dsi_feedback_options', 'dsi_feedback_message');
});

function dsi_feedback_settings_page() {
    ?>
    <div class="wrap">
        <h1>DSI Feedback Email Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('dsi_feedback_options'); ?>
            <?php do_settings_sections('dsi_feedback_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dsi_feedback_from_email">Sender Email</label></th>
                    <td><input type="email" name="dsi_feedback_from_email" value="<?php echo esc_attr(get_option('dsi_feedback_from_email')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dsi_feedback_subject">Email Subject</label></th>
                    <td><input type="text" name="dsi_feedback_subject" value="<?php echo esc_attr(get_option('dsi_feedback_subject')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dsi_feedback_message">Email Message (HTML)</label></th>
                    <td>
                        <textarea name="dsi_feedback_message" rows="10" class="large-text"><?php echo esc_textarea(get_option('dsi_feedback_message')); ?></textarea>
                        <p class="description">Use <code>{name}</code> to insert attendee’s first name.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
