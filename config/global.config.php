<?php
// add your local IP  or other addresses that need access to the service
// for example if want to test / run the deployed bots from your local machine. Use your external IP then. See for example www.watismijnip.nl to fetch it.
// by default the only IP's that have access to the API are the tradingview IP's that are
// exclusively reserved for the webhook function
const WHITELIST_ADDITIONS = [
    '111.222.333.444', // replace those with your own
    '111.222.333.444',
];

// NB this directory should be outside the public web directory!!!
// Example: if public accessible files are hosted in /var/www/html/
// then you could use something like const LOG_FILES_BASE_DIR = '/var/www/frostybot_logs'
// so 1 folder higher than the public www dir
const LOG_FILES_BASE_DIR = __DIR__  . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'frostybot_logs'
?>
