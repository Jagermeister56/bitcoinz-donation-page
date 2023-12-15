<?php

const BTCZ_ADDRESSES = [
    't1fHHnAXxoPWGY77sG5Zw2sFfGUTpW6BcSZ',
    't1L7TtcRPKztgScLnfUToe4sa2aFKf9rQ14',
    't3hTi3fXhcjgjRktoiucUKRtDXxV4GfEL1w',
];
const ETH_ADDRESS = '0x4E3154bc8691BC480D0F317E866C064cC2c9455D';
const BTC_ADDRESS = '1BzBfikDBGyWXGnPPk58nVVBppzfcGGXMx';
const ZEC_ADDRESS = 't1ef9cxzpToGJcaSMXbTGRUDyrp76GfDLJG';
const LTC_ADDRESS = 'LR8bPo7NjPNRVy6nPLVgr9zHee2C7RepKA';
const USDTE_ADDRESS = '0xD36591b20f738f6929272a4391B8C133CB2e5C96';

const CACHE_TEMPLATE = __DIR__ . '/cache/%s.cache';

function debugLog($message) {
    error_log(print_r($message, true));
}

function getCache($key) {
    debugLog("getCache called for key: " . $key);
    $cache_file = sprintf(CACHE_TEMPLATE, $key);
    if (file_exists($cache_file) && (filemtime($cache_file) > (time() - 60))) {
        debugLog("Cache hit for key: " . $key);
        return file_get_contents($cache_file);
    }
    debugLog("Cache miss for key: " . $key);
    return false;
}

function setCache($key, $value) {
    debugLog("setCache called for key: " . $key . " with value: " . $value);
    file_put_contents(sprintf(CACHE_TEMPLATE, $key), $value, LOCK_EX);
}

function getCoinPrice($coin) {
    debugLog("getCoinPrice called for coin: " . $coin);
    $data = file_get_contents('https://api.coingecko.com/api/v3/simple/price?ids='.$coin.'&vs_currencies=usd');
    if ($data === false) {
        debugLog("Failed to retrieve data for coin price: " . $coin);
        return null;
    }
    $data = json_decode($data);
    return (float)($data->$coin->usd);
}

function getBtczBalance() {
    debugLog("getBtczBalance called");
    $total = 0;
    foreach (BTCZ_ADDRESSES as $address) {
        debugLog("Fetching balance for BTCZ address: " . $address);
        $addressTotal = file_get_contents('https://explorer.btcz.rocks/api/addr/' . $address . '/balance');
        if ($addressTotal === false) {
            debugLog("Failed to fetch balance for BTCZ address: " . $address);
            continue;
        }
        $total += $addressTotal;
    }
    $total = $total / 1000000000000000000;
    debugLog("Total BTCZ balance: " . $total);
    setCache('btcz-balance', $total);
    return $total;
}

function getEthBalance() {
    debugLog("getEthBalance called");
    $url = 'https://api.etherscan.io/api?module=account&action=balance&address='. ETH_ADDRESS .'&tag=latest&apikey=' . API_KEY;
    $data = file_get_contents($url);
    if ($data === false) {
        debugLog("Failed to fetch ETH balance");
        return null;
    }
    $data = json_decode($data, true);
    if ($data['status'] !== "1") {
        debugLog("Error in ETH balance response");
        return null;
    }
    $balanceInWei = $data['result'];
    $balanceInEth = bcdiv($balanceInWei, '1000000000000000000', 18);
    debugLog("ETH balance: " . $balanceInEth);
    setCache('eth-balance', $balanceInEth);
    return $balanceInEth;
}

function getBtcBalance() {
    debugLog("getBtcBalance called");
    $data = file_get_contents('http://blockchain.info/q/addressbalance/' . BTC_ADDRESS);
    if ($data === false) {
        debugLog("Failed to fetch BTC balance");
        return null;
    }
    $data = $data / 100000000;
    debugLog("BTC balance: " . $data);
    setCache('btc-balance', $data);
    return $data;
}

function getZecBalance() {
    debugLog("getZecBalance called");
    $data = file_get_contents('https://api.zcha.in/v2/mainnet/accounts/' . ZEC_ADDRESS);
    if (!$data) {
        debugLog("Failed to fetch ZEC balance");
        return null;
    }
    $data = json_decode($data);
    debugLog("ZEC balance: " . $data->balance);
    setCache('zec-balance', $data->balance);
    return $data->balance;
}

function getLtcBalance() {
    debugLog("getLtcBalance called");
    $data = file_get_contents('https://chainz.cryptoid.info/ltc/api.dws?q=getbalance&a=' . LTC_ADDRESS);
    if ($data === false) {
        debugLog("Failed to fetch LTC balance");
        return null;
    }
    debugLog("LTC balance: " . $data);
    setCache('ltc-balance', $data);
    return $data;
}

function getUSDTEBalance() {
    debugLog("getUSDTEBalance called");
    $data = json_decode(file_get_contents("https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress=0xdac17f958d2ee523a2206206994597c13d831ec7&address=" . USDTE_ADDRESS));
    if ((!isset($data->status) && $data->status !== 1) || $data === false) {
        debugLog("Failed to fetch USDTE balance");
        return null;
    }
    debugLog("USDTE balance: " . ($data->result * 0.000001));
    setCache('USDTE-balance', $data->result * 0.000001);
    return $data->result * 0.000001;
}

$response = [
    'btczBalance' => getBtczBalance(),
    'ethBalance' => getEthBalance(),
    'btcBalance' => getBtcBalance(),
    'zecBalance' => getZecBalance(),
    'ltcBalance' => getLtcBalance(),
    'USDTEBalance' => getUSDTEBalance(),
    'btczUsd' => getCoinPrice('bitcoinz'),
    'ethUsd' => getCoinPrice('ethereum'),
    'btcUsd' => getCoinPrice('bitcoin'),
    'zecUsd' => getCoinPrice('zcash'),
    'ltcUsd' => getCoinPrice('litecoin'),
    'USDTEUsd' => getCoinPrice('tether')
];

debugLog("Response: " . json_encode($response));
echo json_encode($response);
