# Email System Plan

## Purpose

This document is the planning reference for adding a customer email system inside the autoshop application.

The goal is to let the shop send useful, controlled, logged emails to customers for service communication, reminders, seasonal greetings, and future campaigns.

Examples:

- Easter greeting
- New Year greeting
- Check-up reminder
- Oil change reminder
- Tire season reminder
- Appointment confirmation
- Vehicle ready for pickup
- Follow-up after completed work order

## Recommended Direction

The application should manage customer email history and consent.

The email delivery can use SMTP or a third-party email provider, but the app should remain the source of truth for:

- Who can receive emails
- What was sent
- When it was sent
- Why it was sent
- Whether the customer unsubscribed
- Which customer, vehicle, or work order the email relates to

Do not rely on raw PHP `mail()` for the long-term system.

Recommended sending methods:

```text
Phase 1: SMTP account
Later: SendGrid, Mailgun, Amazon SES, Postmark, or similar provider
```

## Compliance Notes

This plan should be implemented with Canadian anti-spam rules in mind.

The current official CRTC CASL guidance says commercial electronic messages generally require:

- Consent
- Sender identification and contact information
- A working unsubscribe mechanism

Official references:

- https://web.crtc.gc.ca/eng/internet/anti/reg.htm
- https://www.crtc.gc.ca/eng/com500/faq500.htm
- https://crtc.gc.ca/eng/com500/guide.htm

This document is a technical plan, not legal advice. Before production marketing emails, the shop should confirm the final wording and consent process.

## Email Categories

### Service Emails

Service emails are connected to an active shop process.

Examples:

- Appointment confirmation
- Appointment reschedule notice
- Vehicle arrived confirmation
- Estimate or inspection link
- Vehicle ready for pickup
- Work order follow-up

These should still include clear shop identity and contact information.

### Marketing and Reminder Emails

Marketing/reminder emails are broader customer outreach.

Examples:

- Easter greeting
- New Year greeting
- Seasonal check-up reminder
- Winter tire reminder
- Oil change reminder
- We miss you campaign

These should only go to customers who are allowed to receive them.

The existing `customers.subscribe` field can be the starting signal, but a stronger consent log should be added before serious use.

## Consent Model

The app should track email consent explicitly.

Minimum rules:

- Do not send campaign/reminder emails to customers without an email address.
- Do not send campaign/reminder emails to customers with `subscribe = 0`.
- Do not send campaign/reminder emails to unsubscribed addresses.
- Log how and when consent was captured.
- Allow unsubscribe from every marketing/reminder email.

Recommended consent sources:

```text
frontdesk_verbal
customer_form
online_form
imported_existing_customer
admin_update
unsubscribe_link
```

Recommended consent statuses:

```text
subscribed
unsubscribed
unknown
blocked
```

## Main Workflow

### 1. Customer Gives Consent

Frontdesk asks whether the customer wants to receive service reminders and shop updates by email.

If yes:

```text
customers.subscribe = 1
email_consent_log records consent
```

If no:

```text
customers.subscribe = 0
email_consent_log records refusal or unsubscribe
```

### 2. Admin Creates Template

Admin creates an email template.

Template examples:

```text
New Year greeting
Spring check-up reminder
Oil change reminder
Appointment confirmation
Vehicle ready for pickup
```

Templates should support variables:

```text
{{customer_first_name}}
{{customer_full_name}}
{{vehicle_year}}
{{vehicle_make}}
{{vehicle_model}}
{{vehicle_plate}}
{{last_visit_date}}
{{shop_name}}
{{shop_phone}}
{{unsubscribe_url}}
```

### 3. Admin Creates Campaign

Admin selects:

- Template
- Audience
- Send date/time
- Subject line
- Optional vehicle/service filters

Audience examples:

```text
All subscribed customers
Customers with active vehicles
Customers not seen in 6 months
Customers with no completed work order in 12 months
Customers with a specific vehicle make
Customers due for seasonal tire reminder
```

### 4. Recipient Preview

Before sending, the app shows a recipient preview.

Preview should include:

- Customer name
- Email
- Vehicle, if relevant
- Last visit
- Reason selected
- Subscribe status
- Any exclusion reason

The user should be able to remove individual recipients before sending.

### 5. Send Test Email

Before sending a campaign, the app should require or strongly encourage:

```text
Send Test Email
```

This test goes to the logged-in admin/frontdesk user or a configured shop test address.

### 6. Queue Emails

When approved, the campaign creates queued emails.

Do not send every email in one web request.

Instead:

```text
campaign -> recipients -> queue -> batch sender -> send log
```

This avoids timeouts and gives better logging.

### 7. Send in Batches

The sender processes a limited batch at a time.

Example:

```text
25 emails per run
```

Each email is sent individually.

Never use customer emails in CC or BCC as the main sending strategy.

### 8. Log Results

Each email should be logged as:

```text
queued
sending
sent
failed
skipped
unsubscribed
```

Failure details should be saved for troubleshooting.

### 9. Customer Unsubscribes

Every campaign/reminder email should include an unsubscribe link.

When clicked:

```text
email_unsubscribes records the request
customers.subscribe becomes 0
future campaigns exclude that email
```

The unsubscribe page should be simple and not require login.

## Recommended Roles

### Admin

Admin can:

- Manage email settings
- Create templates
- Edit templates
- Create campaigns
- Preview recipients
- Send test emails
- Start campaigns
- Cancel queued campaigns
- View all email logs
- Manage unsubscribes and consent records

### Frontdesk

Frontdesk can:

- Update customer email consent
- Send service-related emails
- Use approved templates
- View customer email history
- Possibly create simple reminder campaigns if allowed by the shop

### Mechanic

Mechanic should usually not manage campaigns.

Mechanic may view email history connected to a work order if it helps the job.

## Suggested Screens

### Email Dashboard

Show:

- Recent campaigns
- Scheduled campaigns
- Failed emails
- Unsubscribe count
- Recent service emails

### Email Templates

Manage:

- Template name
- Category
- Subject
- Body
- Active/inactive
- Required variables

### New Campaign

Steps:

```text
1. Select template
2. Select audience
3. Preview recipients
4. Send test
5. Schedule or send
```

### Recipient Preview

Show all selected recipients before sending.

Include exclusion reasons:

```text
No email
Unsubscribed
Inactive customer
No active vehicle
Already emailed recently
Invalid email format
```

### Email Queue

Show:

- Queued emails
- Sending status
- Failures
- Retry option

### Customer Email History

On customer detail, show:

- Date sent
- Subject
- Campaign/template
- Status
- Related vehicle
- Related work order
- Unsubscribe status

### Unsubscribe Page

Public page reached from email link.

It should:

- Confirm unsubscribe
- Record the request
- Mark the customer/email as unsubscribed
- Not expose customer data

## Database Direction

The email system should mostly add new tables.

Existing customer data can be reused, especially:

```text
customers.CustomerID
customers.Email
customers.subscribe
customer_vehicle.CVID
work_order.WOID
```

Recommended minimum tables:

```text
email_templates
email_campaigns
email_campaign_recipients
email_queue
email_send_log
email_unsubscribes
email_consent_log
email_settings
```

Possible future tables:

```text
email_bounces
email_click_log
email_open_log
email_template_versions
```

Recommended relationships:

```text
email_campaign_recipients.campaign_id -> email_campaigns.campaign_id
email_campaign_recipients.CustomerID  -> customers.CustomerID
email_campaign_recipients.CVID        -> customer_vehicle.CVID
email_campaign_recipients.WOID        -> work_order.WOID
email_send_log.CustomerID             -> customers.CustomerID
email_send_log.campaign_id            -> email_campaigns.campaign_id
email_consent_log.CustomerID          -> customers.CustomerID
```

## Reminder Logic

### Check-Up Reminder

Possible rule:

```text
Customer has email
Customer is subscribed
Vehicle is active
Last completed work order is older than 6 months
No open work order exists for that vehicle
No check-up reminder sent in the last 90 days
```

### Oil Change Reminder

Possible rule:

```text
Vehicle has completed work order with oil-change related text
Last oil-change related work order is older than configured interval
Customer is subscribed
No recent oil change reminder was sent
```

This may be hard until service categories are structured. Version 1 can use manual campaigns.

### Seasonal Reminder

Possible campaigns:

```text
Spring check-up
Winter tire change
Summer road trip check
Battery check before winter
```

These can start as manual campaigns with recipient filters.

## Safety Rules

- Send one email per recipient.
- Never expose other customer emails.
- Include shop identity and contact information.
- Include unsubscribe link on marketing/reminder emails.
- Log every send attempt.
- Do not send to unsubscribed emails.
- Do not send to invalid email addresses.
- Preview recipients before sending.
- Send a test email before campaign launch.
- Use a queue and batch sender.
- Rate limit sending.
- Keep failed emails visible for review.
- Avoid deleting email logs.

## Suggested First Version

Phase 1 should be practical and controlled:

- Add email settings
- Add email templates
- Add consent log
- Add unsubscribe handling
- Add manual campaign builder
- Add recipient preview
- Add test email
- Add send queue
- Add send log
- Add customer email history

Do not start with fully automatic reminders.

## Future Versions

### Phase 2

- Automatic check-up reminder candidates
- Seasonal campaign presets
- Better audience filters
- Campaign scheduling
- Retry failed sends

### Phase 3

- Provider integration such as SendGrid or Mailgun
- Bounce tracking
- Click tracking
- Template versioning
- Staff approval workflow before sending

### Phase 4

- Appointment confirmation emails
- Appointment reminder emails
- Work order status emails
- Inspection PDF or customer report links
- Customer portal links

## Open Decisions

- Which SMTP or email provider should the shop use?
- Should frontdesk be allowed to send campaigns, or admin only?
- Should existing `customers.subscribe` be treated as full consent or only as a starting flag?
- What shop address and contact information should appear in every email?
- Should reminder campaigns be manual first or scheduled automatically?
- How many emails per batch should be sent?
- Should the app support HTML email, plain text email, or both?
- Should templates support images and shop branding?
- Should unsubscribe apply to all emails or only marketing/reminder emails?

