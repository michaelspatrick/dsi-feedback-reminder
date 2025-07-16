# DSI Seminar Feedback Reminder

**Version:** 1.1  
**Author:** Michael Patrick

This WordPress plugin automatically sends a personalized email to seminar attendees one hour after the seminar ends, requesting feedback and encouraging them to check out the DSI Patreon.

## Features

- Scheduled hourly cron job to check for seminars that ended one hour prior.
- Matches WooCommerce orders by linked product IDs.
- Sends a feedback request email to attendees.
- Prevents duplicate emails by storing per-order meta.
- Includes an admin settings page to customize:
  - Sender email address
  - Email subject line
  - Email body (supports HTML)
- Placeholder `{name}` is replaced with the attendee’s first name.

## Installation

1. Upload the plugin to your `/wp-content/plugins/` directory.
2. Activate it through the WordPress “Plugins” screen.
3. Visit **Settings → DSI Feedback Settings** to configure the email content.

## Customization

### Email Template

You can use the following placeholders in your email message:
- `{name}` — the attendee's first name

HTML is supported in the message body.

### Default Values (if no options set)

- **From email:** `newsletter@dragonsociety.com`
- **Subject:** `We'd love your feedback on the seminar!`
- **Body:**  
  ```html
  <p>Thank you for attending our seminar. We'd love your feedback.</p>
  <p><a href="https://feedback.dragonsociety.com">Click here</a> to share your thoughts.</p>
  <p>Check out our <a href="https://www.patreon.com/c/DragonSocietyInternational">Patreon</a> for exclusive content.</p>
  ```

## Notes

- All emails are sent using WordPress’s native `wp_mail()` function.
- A confirmation email is also sent to `toritejutsu@gmail.com` after each batch.

## License

This plugin is provided as-is without warranty. Customize and extend as needed for your use.

---

© Michael Patrick, Dragon Society International
