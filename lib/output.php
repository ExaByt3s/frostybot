<?php
function getLogDir()
{
    list($scriptPath) = get_included_files();
    $pathItems = explode(DIRECTORY_SEPARATOR, $scriptPath);
    $pairDirName = $pathItems[count($pathItems) - 2];

    if ($pairDirName === 'test') {
        $pairDirName = $pairDirName . DIRECTORY_SEPARATOR . SYMBOL;
    }

    return LOG_FILES_BASE_DIR . DIRECTORY_SEPARATOR . EXCHANGE . DIRECTORY_SEPARATOR . $pairDirName;
}

function getLogFilePath()
{
    return getLogDir() . DIRECTORY_SEPARATOR . 'frostybot.log';
}

function logger($message)
{
    $logDir = getLogDir();
    if (!is_dir($logDir)) {
        // todo: try with catch and good explanation
        mkdir($logDir, 0777, true);
    }
    file_put_contents(getLogFilePath(), date('Y-m-d H:i:s') . ' : ' . $message . PHP_EOL, FILE_APPEND);
    echo date('Y-m-d H:i:s') . ' : ' . $message . PHP_EOL;
}

function debug($message, $makeJson = false)
{
    if ($makeJson === true) {
        echo json_encode((object)$message, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo 'DEBUG: ' . $message . PHP_EOL;
    }
}

function error($message)
{
    logger("ERROR: $message");
    die;
}

function echoJson($object)
{
    echo json_encode((object)$object, JSON_PRETTY_PRINT) . PHP_EOL;
}

function echoLog($numberOfLines)
{
    if (is_numeric($numberOfLines)) {
        echo fixBreaks(join(PHP_EOL, getLastLines(getLogFilePath(), $numberOfLines))) . PHP_EOL;
    } else {
        echo fixBreaks(file_get_contents(getLogFilePath()));
    }
}

function pad($input, $precision, $suffix = '', $length = 14, $suffixLength = 9)
{
    return str_pad(number_format($input, $precision), $length, " ", STR_PAD_LEFT) . ' ' .
        str_pad($suffix, $suffixLength, " ", STR_PAD_RIGHT);
}

function padTitleLeft($input, $length = 5)
{
    return str_pad($input, $length, " ", STR_PAD_LEFT);
}

function padTitle($input, $length = 16)
{
    return str_pad($input, $length, " ", STR_PAD_RIGHT);
}

function echoSummary($summary)
{
    $total = $summary["total"];
    $free = $summary["free"];
    $ask = $summary["ask"];
    $balanceAsk = $summary["balanceAsk"];
    $balanceCurrency = $summary["balanceCurrency"]; // equity currency
    $baseCurrency = $summary["baseCurrency"];       // what currency are we buying/selling
    $contractSize = $summary["contractSize"];
    $orders = $summary["orders"];

    $position = $summary["position"];
    $quoteCurrency = $position["quoteCurrency"];    // with what currency are we 'paying / expressing' the amount of basecurrency to buy/sell
    $positionAmount = $position["amount"];
    $positionEntry = $position["entry"];
    $positionType = $position["type"];
    $positionPnl = $position["pnl"];

    $title = '====================================[ ' . strtoupper(EXCHANGE) . ' : ' . MARKET . ' ]=========================================';
    $result = PHP_EOL . $title . PHP_EOL . PHP_EOL .
        padTitle("$baseCurrency PRICE") . padTitleLeft("USD") .
        pad($ask, 2, "(price of 1 $baseCurrency)") . PHP_EOL . PHP_EOL .

        padTitle('BALANCE') . padTitleLeft($balanceCurrency) .
        pad($total, 5, '(total)') .
        pad($free, 5, '(available)') . PHP_EOL .

        padTitle('') . padTitleLeft('USD') .
        pad($total * $balanceAsk, 2, '(total)') .
        pad($free * $balanceAsk, 2, '(available)') . PHP_EOL . PHP_EOL .

        padTitle('POSITION') . padTitleLeft($quoteCurrency . '') .
        pad($positionAmount * $contractSize, 2, "($positionType)");

    if ($positionAmount > 0) {
        $result .= pad($positionEntry, 2, "(price)") . pad($positionPnl, 2, "(upnl)");
    }
    $result .= PHP_EOL . PHP_EOL;

    $isFirstOrder = true;
    foreach ($orders as $item) {
        if ($isFirstOrder) {
            $result .= padTitle('ORDERS');
        } else {
            $result .= padTitle('');
        }
        $result .= padTitleLeft($quoteCurrency) .
            pad($item["amount"] * $contractSize, 2, '(' . $item["side"] . ')') .
            pad($item["price"], 2, '(price)') . PHP_EOL;
        $isFirstOrder = false;
    }
    if (!$isFirstOrder) $result .= PHP_EOL;
    $result .= str_pad('', strlen($title), '=') . PHP_EOL . PHP_EOL;

    echo(fixBreaks($result));
}


function fixBreaks($input)
{
    return (isset($_SERVER['REMOTE_ADDR'])) ? nl2br($input) : $input;
}

function getLastLines($path, $totalLines)
{
    $lines = array();
    $filePointer = fopen($path, 'r');
    fseek($filePointer, -1, SEEK_END);
    $position = ftell($filePointer);
    $lastLine = "";

    // Loop backwards until we have our lines or we reach the start
    while ($position > 0 && count($lines) < $totalLines) {
        $char = fgetc($filePointer);
        if ($char == PHP_EOL) {
            // skip empty lines
            if (trim($lastLine) != "") {
                $lines[] = $lastLine;
            }
            $lastLine = '';
        } else {
            $lastLine = $char . $lastLine;
        }
        fseek($filePointer, $position--);
    }
    return array_reverse($lines);
}
?>