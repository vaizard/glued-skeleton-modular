<?php

declare(strict_types=1);

namespace Glued\Fin\Classes;

class Utils
{

    public function cz_valid_bank_account($accNumber) {
        $matches = [];
        if (!preg_match('/^(?:([0-9]{1,6})-)?([0-9]{2,10})\/([0-9]{4})$/', $accNumber, $matches)) {
            return false;
        }
        $weights = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];
        $prefix = str_pad($matches[1], 10, '0', STR_PAD_LEFT);
        $main   = str_pad($matches[2], 10, '0', STR_PAD_LEFT);
        // Check prefix
        $checkSum = 0;
        for ($i=0; $i < strlen($prefix); $i++) {
            $checkSum += $weights[$i] * (int)$prefix[$i];
        }
        if ($checkSum % 11 !== 0) {
            return false;
        }
        // Check main part
        $checkSum = 0;
        for ($i=0; $i < strlen($main); $i++) {
            $checkSum += $weights[$i] * (int)$main[$i];
        }
        if ($checkSum % 11 !== 0) {
            return false;
        }
        return true;
    }

    public function cz_number_to_iban(string $accNumber, string $countryCode = 'CZ'): ?string {
        if(!$this->cz_valid_bank_account($accNumber)
            || !in_array($countryCode, ['CZ', 'SK'])
            || !preg_match('/^(?:([0-9]{2,6})-)?([0-9]{2,10})\/([0-9]{4})$/', $accNumber, $matches)
        ) {
            return null;
        }
        $bban = $matches[3].str_pad($matches[1], 6, '0', STR_PAD_LEFT).str_pad($matches[2], 10, '0', STR_PAD_LEFT);
        $iban = 'CZ' . '00' . $bban;
        $iban = iban_set_checksum($iban);
        if (verify_iban($iban,$machine_format_only=true)) {
            return $iban;
        } else {
            return false;
        }
    }

    public $ext_types = [
      'Příjem převodem uvnitř banky' => [
        'electronic' => 1,
        'flow' => 'i',
        'withinbank' => 1,
        'dscr' => 'Inbound electronic transfer within the bank',
      ],
      'Platba převodem uvnitř banky' => [
        'electronic' => 1,
        'flow' => 'o',
        'withinbank' => 1,
        'dscr' => 'Outbound electronic transfer within the bank',
      ],
      'Vklad pokladnou' => [
        'bank-cashier' => 1,
        'flow' => 'i',
        'dscr' => 'Deposit at the cashier',
      ],
      'Výběr pokladnou' => [
        'bank-cashier' => 1,
        'flow' => 'o',
        'dscr' => 'Deposit at the cashier',
      ],
      'Bezhotovostní příjem' => [
        'electronic' => 1,
        'flow' => 'i',
        'dscr' => 'Inbound electronic transfer',
      ],
      'Bezhotovostní platba' => [
        'electronic' => 1,
        'flow' => 'o',
        'dscr' => 'Outbound electronic transfer',
      ],
      'Platba kartou' => [
        'electronic' => 1,
        'card' => 1,
        'flow' => 'o',
        'dscr' => 'Card payment',
      ],
      'Poplatek' => [
        'electronic' => 1,
        'fee' => 1,
        'flow' => 'o',
        'dscr' => 'Fee',
      ],
      'Platba v jiné měně' => [
        'electronic' => 1,
        'flow' => 'o',
        'fx' => 1,
        'dscr' => 'Outbound electronic transfer in foreign currency',
      ],
      'Poplatek - platební karta' => [
        'electronic' => 1,
        'fee' => 1,
        'card' => 1,
        'flow' => 'o',
        'dscr' => 'Fee (card services)',
      ],
      'Inkaso' => [
        'electronic' => 1,
        'cash-collect' => 1,
        'flow' => 'o',
        'dscr' => 'Cash collection',
      ],
      'Okamžitá příchozí platba' => [
        'electronic' => 1,
        'flow' => 'i',
        'instant' => 1,
        'dscr' => 'Inbound instant electronic transfer',
      ],
      'Okamžitá odchozí platba' => [
        'electronic' => 1,
        'flow' => 'o',
        'instant' => 1,
        'dscr' => 'Outbound instant electronic transfer',
      ],
    ];

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
        foreach ($data as $trx) {
            $helper = [];
            $helper['_v'] = '1';
            $helper['account_id'] = $trx['account_id'];

            $helper['status']['req'] = 1;
            $helper['status']['aut'] = 0;
            $helper['status']['trx'] = 0;
            $helper['status']['aud'] = 0;
            if ($trx['paid_status'] == true) $helper['status']['trx'] = 1;

            $helper['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
            $helper['order']['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
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
        foreach ($data as $trx) {
            $trx = (array)$trx;
            $helper = [];
            $helper['_v'] = '1';
            $helper['account_id'] = $meta['account_id'];
            $helper['status']['req'] = 1;
            $helper['status']['aut'] = 1;
            $helper['status']['trx'] = 1;
            $helper['status']['aud'] = 0;
            $helper['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
            $helper['order']['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
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