---
paths:
  - 'app/Mail/**'
  - 'app/Notifications/**'
---
# Mail

## Send email via Mailables/Notifications, queued
Never send mail directly from a controller or service. Send through a Mailable/Notification and queue it if it's slow. (No Mail/Notification usage exists in this app yet — this rule exists so the first one added follows the convention, e.g. a future "document indexing failed" notification to the uploading user.)
