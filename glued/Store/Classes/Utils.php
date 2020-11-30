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
    public function cash($data, $meta, $local_trxs) {
        $crypto = new Crypto;
        foreach ($data as $trx) {
            $helper = [];
            $helper['_v'] = '1';
            $helper['account_id'] = $trx['account_id'];

            $helper['status']['req'] = 1;
            $helper['status']['aut'] = 0;
            $helper['status']['trx'] = 0;
            $helper['status']['aud'] = 0;
            if ($trx['paid_status'] == true) $helper['status']['trx'] = 1;

            $helper['uuid'] = $crypto->genkey_base64();
            $helper['order']['uuid'] = $crypto->genkey_base64();
            $helper['order']['req']['by_name'] = '';
            $helper['order']['req']['by_id'] = $meta['user_id'];
            $helper['order']['req']['dt'] = (new \DateTime($trx['dt']))->format(DATE_W3C);
            $helper['order']['auth']['by_name'] = '';
            $helper['order']['auth']['by_id'] = '';
            $helper['order']['auth']['dt'] = '';

            if (isset($trx['auth_status']))
                $helper['order']['auth']['dt'] = (new \DateTime())->format(DATE_W3C);
                $helper['order']['auth']['by_id'] = $meta['user_id'];
                $helper['order']['auth']['note'] = $trx['auth_note'];
                $helper['status']['aut'] = 1;

            $helper['order']['audit']['by_name'] = '';
            $helper['order']['audit']['by_id'] = '';
            $helper['order']['audit']['dt'] = '';
            $helper['order']['audit']['note'] = '';
            
            $helper['dt'] = $trx['dt'];
            $helper['volume'] = $trx['volume'];
            $helper['currency'] = $trx['currency'];

            $helper['offset']['name'] = $trx['offset_name'];
            $helper['offset']['addr']['unstructured'] = $trx['offset_addr_unstructured'];
            $helper['offset']['id'] = '';
            $helper['offset']['aid'] = $trx['offset_aid']; // assigned id
            $helper['offset']['aid_type'] = $trx['offset_aid_type'];


            $helper['ref']['variable'] = '';
            $helper['ref']['specific'] = '';
            $helper['ref']['freeform'] = $trx['ref_freeform']; // uziv. identifikace
            $helper['ref']['constant'] = '';

            $helper['message'] = $trx['message'] ?? '';        // to recipient
            $helper['comment'] = $trx['comment'] ?? '';        // lokal koment
            $helper['specification'] = '';  // upřesnění (cizoměn)
            $flow = 'i';
            $dscr = 'Inbound cash transaction';
            if ($helper['volume'] < 0) {
                $flow = 'o';
                $dscr = 'Outbound cash transaction';
            }
            $helper['type'] = [
                'dscr' => $dscr,
                'electronic' => 0,
                'flow' => $flow,
            ];
            // TODO: add countermeasures against duplicate entries
            $final[] = $helper;
        }
        return $final;
    }

    /**
     * The fio_cz() function inserts locally unknown/uncached/unprocessed transactions fetched from
     * the fio.cz json API into the database.
     * 
     * @param  array $data       Data fetchched from fio.cz
     * @param  array $meta       Metadata + data constant over all $data items
     * @param  array $local_trxs Local data selected from the database
     * @return array             New (locally unknown) transactions
     */
    public function fio_cz($data, $meta, $local_trxs) {
        $crypto = new Crypto;
        foreach ($data as $trx) {
            $trx = (array)$trx;
            $helper = [];
            $helper['_v'] = '1';
            $helper['account_id'] = $meta['account_id'];
            $helper['status']['req'] = 1;
            $helper['status']['aut'] = 1;
            $helper['status']['trx'] = 1;
            $helper['status']['aud'] = 0;
            $helper['uuid'] = $crypto->genkey_base64();
            $helper['order']['uuid'] = $crypto->genkey_base64();
            $helper['order']['req']['by_name'] = $trx['column9']['value'] ?? '';
            $helper['order']['req']['by_id'] = "";
            $helper['order']['req']['dt'] = "";
            $helper['order']['auth']['by_name'] = $trx['column9']['value'] ?? '';
            $helper['order']['auth']['by_id'] = "";
            $helper['order']['auth']['dt'] = "";
            $helper['order']['auth']['note'] = "";
            $helper['order']['audit']['by_name'] = "";
            $helper['order']['audit']['by_id'] = "";
            $helper['order']['audit']['dt'] = "";
            $helper['order']['audit']['note'] = "";
            $helper['dt'] = (new \DateTime($trx['column0']['value']))->format(DATE_W3C);
            $helper['volume'] = $trx['column1']['value'];
            $helper['currency'] = $trx['column14']['value'];
            if (verify_iban($trx['column2']['value'] ?? '',$machine_format_only=true)) {
                $helper['offset']['account_iban'] = $trx['column2']['value'];
                $helper['offset']['bank_bic'] = $trx['column26']['value'] ?? '';
                if (is_array($trx['column18'])) {
                  $helper['intl']['volume'] = (float)explode(' ', (string)$trx['column18']['value'] ?? '')[0];
                  $helper['intl']['currency'] = explode(' ', (string)$trx['column18']['value'] ?? '')[1];
                  if (((float)$helper['intl']['volume'] ?? 0) != 0) {
                     $helper['intl']['rate'] = abs($helper['volume'] / ((float)$helper['intl']['volume']));
                  }
                }
            } else {
                $helper['offset']['name'] = '';
                $helper['offset']['addr']['unstructured'] = '';
                $helper['offset']['id'] = '';
                $helper['offset']['aid'] = '';
                $helper['offset']['aid_type'] = '';
                $helper['offset']['bank_name'] = $trx['column12']['value'] ?? '';
                $helper['offset']['bank_addr'] = '';
                $helper['offset']['bank_code'] = $trx['column3']['value'] ?? '';
                $helper['offset']['bank_bic'] = $trx['column26']['value'] ?? '';
                $helper['offset']['account_number'] = $trx['column2']['value'] ?? '';
                $helper['offset']['account_name'] = $trx['column10']['value'];
                $helper['offset']['account_iban'] = $this->cz_number_to_iban($helper['offset']['account_number'].'/'.$helper['offset']['bank_code']);
            }
            $helper['ref']['variable'] = $trx['column5']['value'] ?? '';
            $helper['ref']['specific'] = $trx['column6']['value'] ?? '';
            $helper['ref']['freeform'] = $trx['column7']['value'] ?? ''; // uziv. identifikace
            $helper['ref']['constant'] = $trx['column4']['value'] ?? '';
            $helper['message'] = $trx['column16']['value'] ?? '';        // to recipient
            $helper['comment'] = $trx['column25']['value'] ?? '';        // lokal koment
            $helper['specification'] = $trx['column18']['value'] ?? '';  // upřesnění (cizoměn)
            $helper['ext']['trx_id'] = $trx['column22']['value'];
            $helper['ext']['order_id'] = $trx['column17']['value'] ?? ''; 
            $helper['ext']['order_by'] = $trx['column9']['value'] ?? ''; 
            $helper['ext']['type'] = $trx['column8']['value'] ?? '';     // type
            if (array_key_exists($helper['ext']['type'], $this->ext_types)) {
                $helper['type'] = $this->ext_types[$helper['ext']['type']];  
            } else {
                $flow = 'i';
                if ($helper['volume'] < 0) {
                    $flow = 'o';
                }
                $helper['type'] = [
                    'dscr' => 'Other / unknown',
                    'flow' => $flow,
                ];
            }

            // Add only new (locally unknown) to the result set
            // TODO: add support for rescyncing data with updates to existing local data
            $found = array_search($helper['ext']['trx_id'], array_column( $local_trxs, 'c_ext_trx_id'));
            if ($found === false) {
                $final[] = $helper;
            }
        }
      return $final ?? [];
    }


}