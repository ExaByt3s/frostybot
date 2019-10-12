<?php
const EXCHANGE = 'deribit';                                             // Any exchange supported by CCXT
const EXCHANGE_API_BASE = '';                                           // override the exchange api base url to use. Sometimes an exchange offers alternative failover urls
                                                                        // Set to empty string for the default api endpoint
                                                                        // example: https://hermes.deribit.com (without the api path)

// if using sub-accounts per bot (recommended), leave empty and use subaccount API keys in config.php)
const API_KEY = '';                                                     // main API key, for subaccounts see config.php
const API_SECRET = '';                                                  // main API secret, or subaccounts see config.php
?>
