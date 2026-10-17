<?php
// config/xendit.php
// Use TEST keys while developing. Never commit real keys (add this file to .gitignore).

return [
    // Dashboard > Settings > Developers > API Keys (Secret key, starts with xnd_development_ or xnd_production_)
    'secret_key'     => getenv('XENDIT_SECRET_KEY') ?: 'xnd_development_33JSo9HjGORByAEbdHaubfsROVDHrGW76bSnbuu5D1gcAdr5fcoGyJnDln55a0',

    // Dashboard > Settings > Developers > Webhooks > "Verification Token"
    'callback_token' => getenv('XENDIT_CALLBACK_TOKEN') ?: 'u9AY63GmOrNMwe5cOc05mGMvjAcoSAmLpJ6LextK0ZD6Yms4',

    // Same base URL for test and live. The key you use decides the mode.
    'base_url'       => 'https://api.xendit.co',

    'currency'         => 'PHP',
    'invoice_duration' => 86400, // seconds before the invoice expires (24 hours)

    // Set these to the public frontend URLs in production.
    'success_url'    => getenv('XENDIT_SUCCESS_URL') ?: 'http://localhost:5173/xendit-success',
    'failure_url'    => getenv('XENDIT_FAILURE_URL') ?: 'http://localhost:5173/xendit-cancel',
];