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

    public function cash($data, $local_trxs) {
        $helper = [];
        $helper['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
        $helper['order']['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
        $helper['order']['req_by_name'] = $trx['column9']['value'] ?? '';
        $helper['order']['req_by_uid'] = "";
        $helper['order']['req_dt'] = "";
        $helper['order']['auth_by_name'] = $trx['column9']['value'] ?? '';
        $helper['order']['auth_by_uid'] = "";
        $helper['order']['auth_dt'] = "";
        $helper['order']['auth_note'] = "";
        $helper['order']['audit_by_name'] = "";
        $helper['order']['audit_by_uid'] = "";
        $helper['order']['audit_dt'] = "";
        $helper['order']['audit_note'] = "";
        $helper['dt'] = (new \DateTime()->format(DATE_W3C);
        $helper['volume'] = $trx['column1']['value'];
        $helper['currency'] = $trx['column14']['value'];
        $helper['offset']['name'] = '';
        $helper['offset']['address'] = '';
        $helper['offset']['id'] = '';
        $helper['offset']['uid'] = '';
        $helper['ref']['variable'] = $trx['column5']['value'] ?? '';
        $helper['ref']['specific'] = $trx['column6']['value'] ?? '';
        $helper['ref']['internal'] = $trx['column7']['value'] ?? ''; // uziv. identifikace
        $helper['ref']['constant'] = $trx['column4']['value'] ?? '';
        $helper['message'] = $trx['column16']['value'] ?? '';        // to recipient
        $helper['comment'] = $trx['column25']['value'] ?? '';        // lokal koment
        $helper['specification'] = $trx['column18']['value'] ?? '';  // upřesnění (cizoměn)
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
        return $helper;
    }

    public function fio_cz($data, $local_trxs) {
        foreach ($data as $trx) {
            $trx = (array)$trx;
            $helper = [];
            $helper['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
            $helper['order']['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
            $helper['order']['req_by_name'] = $trx['column9']['value'] ?? '';
            $helper['order']['req_by_uid'] = "";
            $helper['order']['req_dt'] = "";
            $helper['order']['auth_by_name'] = $trx['column9']['value'] ?? '';
            $helper['order']['auth_by_uid'] = "";
            $helper['order']['auth_dt'] = "";
            $helper['order']['auth_note'] = "";
            $helper['order']['audit_by_name'] = "";
            $helper['order']['audit_by_uid'] = "";
            $helper['order']['audit_dt'] = "";
            $helper['order']['audit_note'] = "";
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
                $helper['offset']['address'] = '';
                $helper['offset']['id'] = '';
                $helper['offset']['uid'] = '';
                $helper['offset']['bank_name'] = $trx['column12']['value'] ?? '';
                $helper['offset']['bank_address'] = '';
                $helper['offset']['bank_code'] = $trx['column3']['value'] ?? '';
                $helper['offset']['bank_bic'] = $trx['column26']['value'] ?? '';
                $helper['offset']['account_number'] = $trx['column2']['value'] ?? '';
                $helper['offset']['account_name'] = $trx['column10']['value'];
                $helper['offset']['account_iban'] = $this->cz_number_to_iban($helper['offset']['account_number'].'/'.$helper['offset']['bank_code']);
            }
            $helper['ref']['variable'] = $trx['column5']['value'] ?? '';
            $helper['ref']['specific'] = $trx['column6']['value'] ?? '';
            $helper['ref']['internal'] = $trx['column7']['value'] ?? ''; // uziv. identifikace
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