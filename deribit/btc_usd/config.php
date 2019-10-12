<?php
// bot instance settings
const ENABLED = true;                           // set to false to temporarily disable this currency pair
const MARKET = 'BTC-PERPETUAL';                 // Market to trade
const SYMBOL = 'BTC-PERPETUAL';                 // needed as some exchanges use different values for market and symbol
const DEFAULT_ORDER_SIZE = 1000;                // Default Order size in USD (if not specified in GET)
const MAX_POSITION_AS_PERC_OF_EQUITY = 500;     // maximum  position as a percentage of total balance. > 100 means bigger position than total equity allowed --> a leveraged position

// it's recommended to use sub accounts for bots to limit your risk, but it's not needed and up to you.
// So you can leave then next fields empty if you configured your key/secret in exchange.config.php
const SUBACCOUNT_API_KEY = '';                  // API key for subaccounts (or use in case you use a separate main account for this specific bot)
const SUBACCOUNT_API_SECRET = '';               // API secret for subaccounts (or use in case you use a separate main account for this specific bot)
?>

