<?php

declare(strict_types=1);

namespace Glued\Store\Classes;

use Glued\Core\Classes\Crypto\Crypto;

class Utils
{


    /**
     * The cash() function inserts a new transaction as sent with the POST request.
     * the fio.cz json API into the database.
     * 
     * @param  array $data       Data from the POST request
     * @param  array $meta       Metadata + data constant over all $data items
     * @param  array $local_trxs Local data selected from the database
     * @return array             Properly structured data
     */
    public function seller($data, $meta, $local_trxs) {
        $helper['_s'] = 'store/sellers';
        $helper['_v'] = '1';

        $helper['business']['name'] = $data['business_name'];
        $helper['business']['regid'] = $data['business_regid'];
        $helper['business']['vatid'] = $data['business_vatid'];
        $helper['business']['vatpayer'] = $data['business_vatpayer'] ?? false;
        $helper['business']['addr'] = $data['business_addr'];

        $helper['template'] = $data['template'];
        $helper['uri'] = $data['uri'];
        $helper['contacts'] = $data['contacts'];
        $helper['domain'] = $data['domain'];

        return $helper ?? null;
    }


}