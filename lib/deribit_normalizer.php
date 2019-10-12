<?php
/////////////////////////////////////////////////////////
// exchange specific stuff, that is not unified in cctx
//
// only place where we hardcode some values for fixes
/////////////////////////////////////////////////////////
//
//  todo: create a normalizer class that implements an interface
//
function normalizedBalance($exchange, $baseCurrency)
{
    return $exchange->fetch_balance(array(currency => $baseCurrency));
}

function normalizedBalanceCurrency($baseCurrency) {
    return $baseCurrency;
}

function normalizedBalanceAsk($baseAsk, $exchange) {
    return $baseAsk;
}

function normalizedContractSize($exchange)
{
    $contractSize = 1;
    if ($exchange->version === 'v1' and MARKET === 'BTC-PERPETUAL') {
        $contractSize = 10;
    }
    return $contractSize;
}

function getPositionSummary($position, $ask) {
    $normalizedPosition = normalizedPositionInContracts($position);

    $result = array();
    $result["quoteCurrency"] = "USD";
    $result["amount"] = abs($normalizedPosition);
    $result["type"] =  $normalizedPosition < 0 ? 'short' : ($normalizedPosition > 0  ? 'long' : 'no position');
    $result["entry"] = isset($position["averagePrice"]) ? $position["averagePrice"] : '';
    $result["pnl"] = $position["profitLoss"] * $ask;

    return $result;
}

function normalizedPosition($exchange)
{
    // todo: create temp results array with needed data
    $position = null;
    $positions = $exchange->private_get_positions();
    foreach ($positions['result'] as $positionRaw) {
        if ($positionRaw['instrument'] === SYMBOL) {
            $position = $positionRaw;
            break;
        }
    }
    return $position;
}

function normalizedPositionInContracts($position)
{
    $amount = $position['size'];
    return is_numeric($amount) ? $amount : 0;
}
?>