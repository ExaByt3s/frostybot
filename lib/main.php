<?php
// https://ccxt.readthedocs.io/en/latest/README.html
include(__DIR__ . '/ccxt/ccxt.php');
include(__DIR__ . '/output.php');
include(__DIR__ . '/' . EXCHANGE . '_normalizer.php');

//////////////////////////
// constants
//////////////////////////

// GET/POST whitelist addresses (from TradingView, should not be changed by user so keeping em outside the config files)
const WHITELIST = [
    '52.89.214.238',
    '34.212.75.30',
    '54.218.53.128',
    '52.32.178.7',
];


//////////////////////////
// functions
//////////////////////////

function getOrderSize($size, $command)
{
    if (!isset($size)) {
        if ($command === "CLOSE") {
            return '100%';
        } else if ($command === "LONG" || $command === "SHORT") {
            return DEFAULT_ORDER_SIZE;
        } else if ($command === "LOG") {
            return 10;
        }
    }
    return $size;
}

function validateNumber($input, $errorMessage)
{
    if (!is_numeric($input) || $input == 0) {
        error($errorMessage);
    }
}

//////////////////////////
// main logic
//////////////////////////

// ignore requests when bot temporary disabled
if (ENABLED !== true) {
    error('Bot is disabled in config. Ignoring request.');
}

// Check that request is coming from an authorised Trading View IP
if (isset($_SERVER['REMOTE_ADDR']) and !in_array($_SERVER['REMOTE_ADDR'], WHITELIST) and !in_array($_SERVER['REMOTE_ADDR'], WHITELIST_ADDITIONS)) {
    error('Request received from invalid address: ' . $_SERVER['REMOTE_ADDR']);
}

// Connect to Exchange
$exchange_class = "\\ccxt\\" . EXCHANGE;
$exchange = new $exchange_class(array(
    'apiKey' => (SUBACCOUNT_API_KEY !== '') ? SUBACCOUNT_API_KEY : API_KEY,
    'secret' => (SUBACCOUNT_API_SECRET !== '') ? SUBACCOUNT_API_SECRET : API_SECRET
));

// override the default api endpoint base url
if (is_string(EXCHANGE_API_BASE) and EXCHANGE_API_BASE !== '') {
    $exchange->urls['api'] = EXCHANGE_API_BASE;
};

$exchange->load_markets();
$ticker = $exchange->fetch_ticker(MARKET);
$market = $exchange->markets[MARKET];
$baseCurrency = $market["base"];
$contractSize = normalizedContractSize($exchange);

// this is needed if we want to switch from a short to a long or vice versa (flip position)
// with this we can make a calculation to do it do it in 1 command, otherwise we have to do it in 2 (first close and then new order)
$normalizedBalanceCurrency = normalizedBalanceCurrency($baseCurrency);
$balance = normalizedBalance($exchange, $normalizedBalanceCurrency);
$balanceAsk = normalizedBalanceAsk($ticker['ask'], $exchange);
$position = normalizedPosition($exchange);
// todo: more generalization is needed for balance calculations if we want to support  non perpetual like: BNB/ATOM or USDT/XLM like pairs on a shitcoin  exchange
// todo needed? maybe better to normalize position before calculations and use position there
$currentPositionInContracts = normalizedPositionInContracts($position);
$totalBalance = $balance[$normalizedBalanceCurrency]['total'];
$totalBalanceUsd = $balanceAsk * $totalBalance;
$isLong = ($currentPositionInContracts > 0);
$isShort = ($currentPositionInContracts < 0);

// process script arguments
// precedence: post > get > command line
$rawPostText = file_get_contents('php://input');
$usePostArgs = $rawPostText !== "";
$useGetArgs = (!$usePostArgs and isset($_GET['command'])); // fucking php operator precedence... without () this breaks
$useCommandLineArgs = (!$usePostArgs and !$useGetArgs);

$command = null;
$size = null;
$price = null;
$sizeIsSet = false;

if ($usePostArgs) {
    $postArgs = explode(" ", $rawPostText);
    $command = strtoupper($postArgs[0]);
    $size = getOrderSize($postArgs[1], $command);
    $sizeIsSet = isset($postArgs[1]);
    $price = $postArgs[2];

} else if ($useGetArgs) {
    $command = strtoupper($_GET['command']);
    $size = getOrderSize($_GET['size'], $command);
    $sizeIsSet = isset($_GET['size']);
    $price = $_GET['price'];

} else if ($useCommandLineArgs) {
    $command = strtoupper($argv[1]);
    $size = getOrderSize($argv[2], $command);
    $sizeIsSet = isset($argv[2]);
    $price = $argv[3];

} else {
    error('No nothing to do. Gimme some arguments first.');
}

// pre-process the buying and selling commands
if ($command === "LONG" || $command === "SHORT" || $command === "CLOSE") {

    // process $size. It may be incorrect or it may need adjustment because of current position
    if (substr(strtolower($size), 0, 1) === '-') {
        error('negative values not supported. "short -1000" must be entered as "long 1000" etc.');
    }

    // todo: ref to USD is still ugly but will do for now as everything is perpetual now
    $absCurrentPositionInUsd = abs($currentPositionInContracts * $contractSize);
    $maxPositionPerc = MAX_POSITION_AS_PERC_OF_EQUITY / 100;

    // process $size specified as percentage
    if (substr(strtolower($size), -3) === 'pct' || substr(strtolower($size), -1) === '%') {

        // support % as well as pct as extension for orders in percentage
        $sizeInPercent = str_replace(['pct', '%'], '', strtolower($size));
        validateNumber($sizeInPercent, "Invalid order percentage size: $sizeInPercent");
        $perc = $sizeInPercent / 100;

        if ($command === 'CLOSE') { // for a CLOSE  the percentage is taken relative to the open position, with a max of 100%
            if ($perc > 1) {
                error('You cannot close more than 100% of a position.');
            }
            $size = $absCurrentPositionInUsd * $perc;

        } else { // for LONG/SHORT the percentage is taken of total account equity
            if ($perc > $maxPositionPerc) {
                error('Max position allowed is ' . MAX_POSITION_AS_PERC_OF_EQUITY . '% as specified in the config.');
            }

            $size = $totalBalanceUsd * $perc;

            // we may have to adjust the order size as we already have a position. we do that automatically if possible
            if ($isShort && $command === 'SHORT' || $isLong && $command === 'LONG') {
                $currentPositionPerc = $absCurrentPositionInUsd / $totalBalanceUsd;
                $potentialNewPerc = ($size + $absCurrentPositionInUsd) / $totalBalanceUsd;

                if ($potentialNewPerc > $maxPositionPerc) {
                    $availablePerc = $maxPositionPerc - $currentPositionPerc;

                    // check if we can add the difference
                    if ($availablePerc * $totalBalanceUsd >= $contractSize) {
                        logger("Adjusting order because it exceeds the maximum position relative to total equity. New amount to add to the position is: " . round($availablePerc * 100, 2) . "%");
                        $size = $totalBalanceUsd * $availablePerc;
                        debug("New size is: " . round($size));

                    } else {
                        error('You already have the maximum allowed position. Ignoring order.');
                    }
                }
            }
        }

        // process $size provided as fixed amount
    } else {
        validateNumber($size, "invalid order size: $size");
        $maxSize = $maxPositionPerc * $totalBalanceUsd;

        if ($command === 'CLOSE') { // for a CLOSE  the amount is taken in relation to the open position

            if ($size > $absCurrentPositionInUsd and $sizeIsSet) {
                error('Cannot close more than 100% of position. Maximum amount for close is:' . $absCurrentPositionInUsd);
            }

        } else { // for LONG/SHORT the max amount is relative to the total balance

            if ($size > $maxSize) {
                error('Max position addition allowed is: ' . floor($maxSize) . 'USD (for a max position of ' . MAX_POSITION_AS_PERC_OF_EQUITY . '% of total equity).');

                // we may have to alter the position size
            } else if ($isShort && $command === 'SHORT' || $isLong && $command === 'LONG') {

                if ($absCurrentPositionInUsd + $size > $maxSize) {
                    $size = $maxSize - $absCurrentPositionInUsd;
                    if ($size > 0) {
                        logger("Adjusting order because it exceeds the maximum position relative to total equity. ");
                    } else {
                        error('You already have the maximum allowed position. Ignoring order.');
                    }
                }
            }
        }
    }

    $orderSize = floor($size / $contractSize);
}

$order = false;

// Delegate appropriate command to the Exchange
switch ($command) {
    case 'LONG':
        $order = $isShort && !$price
            ? abs($currentPositionInContracts) + $orderSize
            : $orderSize;
        break;
    case 'SHORT':
        $order = $isLong && !$price
            ? -$currentPositionInContracts - $orderSize
            : -$orderSize;
        break;
    case 'CLOSE':
        if ($isShort) {
            $order = $sizeIsSet
                ? abs($orderSize)
                : abs($currentPositionInContracts);
        } else if ($isLong) {
            $order = $sizeIsSet
                ? -abs($orderSize)
                : -$currentPositionInContracts;
        }
        break;
    case 'TICKER':
        echoJson($ticker);
        break;
    case 'BALANCE':
        echoJson($balance);
        break;
    case 'TRADES':
        $trades = $exchange->fetch_my_trades(MARKET);
        echoJson($trades);
        break;
    case 'ORDERS':
        $orders = $exchange->fetch_open_orders(MARKET);
        echoJson($orders);
        break;
    case 'CANCEL':
        $orders = $exchange->fetch_open_orders(MARKET);
        foreach ($orders as $item)
            $exchange->cancel_order($item['id']);
        echoJson($orders);
        break;
    case 'POSITION':
        echoJson($position);
        break;
    case 'LOG':
        echoLog($size);
        break;
    case 'SUMMARY':
        $summary = $balance[$normalizedBalanceCurrency]; // todo: should be a clone, not a ref
        $summary["ask"] = $ticker['ask'];
        $summary["baseCurrency"] = $baseCurrency;
        $summary["balanceCurrency"] = $normalizedBalanceCurrency;
        $summary["balanceAsk"] = $balanceAsk;
        $summary["position"] = getPositionSummary($position, $ticker['ask']);
        $summary["orders"] = $exchange->fetch_open_orders(MARKET);
        $summary["contractSize"] = $contractSize;
        echoSummary($summary);
        break;
    default:
        debug("Unknown command: $command");
}

if (!$order && $command === 'CLOSE') {
    logger('Skipping close as there are no open orders.');

} else if ($order) {
    $type = ((strtoupper($price) !== 'MARKET') && (is_numeric($price))) ? 'limit' : 'market';
    $dir = $order > 0 ? 'buy' : ($order < 0 ? 'sell' : null);

    try {
        $result = $exchange->create_order(MARKET, $type, $dir, abs($order), $price);
        if ($result) {
            debug($result, true);
        }
        // todo: move to output.php
        logger(
            'TRADE: USD ' .
            str_pad($order * $contractSize, 8, " ", STR_PAD_LEFT) .
            ',   BALANCE: ' . $normalizedBalanceCurrency .
            str_pad(round($totalBalance, 5), 10, " ", STR_PAD_LEFT) .
            ',   USD ' .
            str_pad(round($totalBalanceUsd, 2), 10, " ", STR_PAD_LEFT)
        );

    } catch (Exception $e) {
        error($e->getMessage());
    }
}
?>
