<?php
/////////////////////////////////////////////////////////
// exchange specific stuff, that is not unified in cctx
//
// only place where we hardcode some values for fixes
/////////////////////////////////////////////////////////
//
//  todo: create a normalizer class that implements an interface
//

// bitmex does everything in BTC, while others may have separate balance currencies like Deribit
function normalizedBalance($exchange, $baseCurrency)
{
    return $exchange->fetch_balance();
}

// bitmex does everything in BTC, while others may have separate balance currencies like Deribit
function normalizedBalanceCurrency($baseCurrency)
{
    return 'BTC';
}

function normalizedBalanceAsk($baseAsk, $exchange)
{
    $tickerBTC = $exchange->fetch_ticker('BTC/USD');
    return $tickerBTC["ask"];
}

function normalizedContractSize($exchange)
{
    return 1;
}

function getPositionSummary($position, $ask)
{
    $normalizedPosition = normalizedPositionInContracts($position);

    $result = array();
    $result["quoteCurrency"] = isset($position["quoteCurrency"]) ? $position["quoteCurrency"] : '';
    $result["amount"] = abs($normalizedPosition);
    $result["type"] = $normalizedPosition < 0 ? 'short' : ($normalizedPosition > 0 ? 'long' : 'no position');
    $result["entry"] = isset($position["avgEntryPrice"]) ? $position["avgEntryPrice"] : '';
    $result["pnl"] = (($position["unrealisedPnl"] + $position["realisedPnl"])/100000000) * $ask;

    return $result;
}

function normalizedPosition($exchange)
{
    $position = null;
    $positions = $exchange->private_get_position(array(symbol => SYMBOL));
    foreach ($positions as $positionRaw) {
        if ($positionRaw['symbol'] === SYMBOL) {
            $position = $positionRaw;
            break;
        }
    }
    $position["amount"] = $position['currentQty'];
    return $position;
}

function normalizedPositionInContracts($position)
{
    return is_numeric($position['currentQty']) ? $position['currentQty'] : 0;
}

?>