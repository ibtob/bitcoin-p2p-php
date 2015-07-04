<?php

require "../vendor/autoload.php";

use BitWasp\Bitcoin\Networking\P2P\PeerLocator;
use BitWasp\Bitcoin\Networking\MessageFactory;
use BitWasp\Bitcoin\Networking\Structure\NetworkAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\P2P\Peer;
use BitWasp\Buffertools\Buffer;

$loop = React\EventLoop\Factory::create();
$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);
$connector = new React\SocketClient\Connector($loop, $dns);

$network = Bitcoin::getDefaultNetwork();

$local = new NetworkAddress(
    Buffer::hex('01', 16),
    '192.168.192.39',
    32301
);

$msg = new MessageFactory($network, new \BitWasp\Bitcoin\Crypto\Random\Random());
$locator = new PeerLocator($local, $msg, $connector, $loop, true);

function decodeInv(Peer $peer, \BitWasp\Bitcoin\Networking\Messages\Inv $inv)
{
    $txs = [];
    $filtered = [];
    $blks = [];

    foreach ($inv->getItems() as $item) {
        $loc = null;
        if ($item->isBlock()) {
            $loc = &$blks;
        } else if ($item->isTx()) {
            $loc = &$txs;
        } else if ($item->isFilteredBlock()) {
            $loc = &$filtered;
        }
        $loc[] = $item->getHash();
    }

    echo " [txs: " . count($txs) . ", blocks: " . count($blks) . ", filtered: " . count($filtered) . "]\n";
}

$locator->discoverPeers()->then(function (PeerLocator $locator) {
    $manager = new \BitWasp\Bitcoin\Networking\P2P\PeerManager($locator);
    $manager->connectToPeers(1)->then(function ($peers) {
        $peer = $peers[0];
        /** @var Peer $peer */
        $peer->on('inv', 'decodeInv');
    });
});

$loop->run();