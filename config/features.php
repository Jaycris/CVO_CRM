<?php

return [
    // Only the CRM mailbox UI and sync. Transactional system mail remains configured in mail.php.
    'mailbox_enabled' => env('CRM_MAILBOX_ENABLED', false),
];
