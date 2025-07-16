<?php
/*
Plugin Name: DSI Seminar Feedback Reminder
Description: Sends seminar attendees an email requesting feedback one hour after the seminar ends.
Version: 1.0
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
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                if (!in_array($product_id, $product_ids)) continue;

                $meta_key = '_dsi_feedback_sent_' . $event_id;
                if ($order->get_meta($meta_key)) continue; // Already sent for this event

                $email = $order->get_billing_email();
                $name = $order->get_billing_first_name();

                $subject = "We'd love your feedback on the seminar!";
                $body = "<p>Hi {$name},</p>
                         <p>Thank you for attending our seminar. We'd love to hear your thoughts so we continue to improve.</p>
                         <p>Your feedback ensures we customize the seminar experience to be the best it can possibly be.</p>
                         <p><a href='https://feedback.dragonsociety.com' target='_blank'>Click here to give your feedback</a>.</p>
                         <p>Also, check out exclusive content on our <a href='https://www.patreon.com/c/DragonSocietyInternational' target='_blank'>Patreon page</a>.</p>
                         <p>- Dragon Society International</p>";

                wp_mail($email, $subject, $body, [
                    'Content-Type: text/html; charset=UTF-8'
                ]);

                $order->update_meta_data($meta_key, 1);
                $order->save();
            }
        }
        // Send an email to Mike so he knows it was sent
        wp_mail("toritejutsu@gmail.com", $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ]);  
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

