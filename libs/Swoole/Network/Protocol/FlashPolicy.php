<?php
namespace Swoole\Network\Protocol;

use Swoole;
class FlashPolicy extends Swoole\Network\Protocol implements Swoole\Server\Protocol
{
    public $default_port = 843;
    public $policy_xml = '<cross-domain-policy>
	<site-control permitted-cross-domain-policies="all"/>
	<allow-access-from domain="*" to-ports="1000-9999" />
</cross-domain-policy>';

    function onReceive($server,$client_id, $from_id, $data)
    {
        echo $data;
        $this->server->send($client_id,$this->policy_xml);
        $this->server->close($client_id);
    }

    function onStart($server)
    {

    }
    function onConnect($server, $client_id, $from_id) {

    }
    function onClose($server, $client_id, $from_id) {

    }
    function onShutdown($server) {

    }
}