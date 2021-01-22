<?php

declare(strict_types=1);

namespace Glued\Integrations\Classes;

use Slim\Exception\HttpInternalServerErrorException;

class Google
{

    protected $db;


    public function __construct($db) {
        $this->db = $db;
    }

    
    /*
    //      "sheets.checkmeta": {       // php funkce, ktera kontroluje, zda existuji predepsane zahlavi sloupcu (v radku definovanem pomoci "meta")
    //         "meta": "Orig!A1:G1",
    //         "reqs": [ "DÚZP", "VS", "VS2" ]
    //      }
    
    //je funkce, kde si reknes, ze nekde v definovanem rozsahu jsou bunky co obsahuji neco
    funkce( "Orig!A1:G1", [ "DÚZP", "VS", "VS2" ] , &$ret = [] ) : mixed {
        return true ; kdyz tam ty bunky sedi
        return false kdyz ne;
    }
    do $ret bych nacpal prirazeni
    */
    
    public function checkmeta($service, $spreadsheetId, $meta, $data, &$ret = []) {
        try {
            $gresponse = $service->spreadsheets_values->get($spreadsheetId, $meta);
            $values = $gresponse->getValues();
            $nazvy_sloupcu = $values[0];
            //$ret[] = $nazvy_sloupcu;
            
            // data predpokladame ze maji tvar json pole, takze prevedeme na pole
            $data_array = json_decode($data, true);
            //$ret[] = 'pole data: '.print_r($data_array, true);
            
            // najdeme ke kazdemu sloupci v datech pozici
            $finalne_vracime = array();
            $pocet_nenalezenych = 0;
            foreach ($data_array as $sloupec) {
                // najdeme umisteni $sloupec v poli $nazvy_sloupcu
                $hledam_index = array_search($sloupec, $nazvy_sloupcu);
                if ($hledam_index === false) {
                    $finalne_vracime[$sloupec] = -1;
                    $pocet_nenalezenych++;
                }
                else {
                    $finalne_vracime[$sloupec] = $hledam_index;
                }
            }
            $ret = $finalne_vracime;
            
            if ($pocet_nenalezenych > 0) {
                return false;
            }
            else {
                return true;
            }
        } catch (\Exception $e) {
            //$ret = get_class_methods($e);
            $ret = $e->getErrors();
            throw new HttpInternalServerErrorException($request, $e->getMessage());
        }
    }
    
    
    
    
    /*
    uklada az ta dalsi funkce
        //      "sheets.rowcache": {       // php funkce, ktera cachene data do nasi tabulky - nejdriv udela ze vseho ve sloupecku fuid md5 a testne, ze jsou hashe fakt unikatni
        //         "meta": "Orig!A1:G1",
        //         "data": "Orig!A2:G5",
        //         "fuid": "DÚZP",
        //       },
    
takze budes mit google_rowcache() ... parametry bych asi zmenil proti tomu co jsem psal do te dokumentace ...
data bych ponechal - jako datovy rozsah. ale asi by spis melo byt misto meta predane  [ "DÚZP", "VS", "VS2" ]
Poslal(a) Pavel, 13. leden v 12:26
nez rozsah
Poslal(a) Pavel, 13. leden v 12:26
a to ze ktereho sloupecku brat ktera data by se bzalo podle predaneho $ret z prvni funkce
Poslal(a) Pavel, 13. leden v 12:26
mozna ... i kdyz to muze byt hodne specificky usecase
Poslal(a) Pavel, 13. leden v 12:26
ten ted ice buem potrebovat, ale asi by potom nebylo od veci mit tu rowcache funkci jeste nejakou takovou ze nebude prirazovat jmena sloupeckum a proste to cachne jak to zrovna lezi a bezi.
    
    */
    
    public function rowcache($service, $spreadsheetId, $object_id, $vysledek_checkmeta, $data, $fuid, &$ret = []) {
        try {
            $gresponse = $service->spreadsheets_values->get($spreadsheetId, $data);
            $values = $gresponse->getValues();
            
            // ted mame nactene vsecky data ve stejem rozsahu sloupcu jako puvodne $vysledek_checkmeta
            // projdem vsecky radky a zpracujeme je do tabulky t_int_cache
            
            // nejdriv si urcime index fuid sloupecku
            // TODO, melo by byt mozne urcit i vice sloupecku jako fuid, pak to tady bude slozitejsi
            $fuid_index = $vysledek_checkmeta[$fuid];
            $ret[] = 'fuid index je '.$fuid_index;
            foreach ($values as $radek) {
                // hash fuidu
                $fuid_hash = md5($radek[$fuid_index]);
                $ret[] ='hodnota '.$radek[$fuid_index].', hash '.$fuid_hash;
                // pripravime pole a json s daty
                $pole_dat = array();
                // $vysledek_checkmeta ma klice nazvy sloupecku a hodnoty jako indexy (pozice) sloupecku na radku
                foreach ($vysledek_checkmeta as $kk => $vv) {
                    $pole_dat[$kk] = $radek[$vv];
                }
                $json_data = json_encode($pole_dat);
                $ret[] = 'json je '.$json_data;
                // hash dat
                $data_hash = md5($json_data);
                $ret[] = 'hash dat je '.$data_hash;
                // nacteme to z db. vezmeme nejvyssi rev a staci nam one, takze pokud to tam je, nacteme rovnou tu se kteroubudeme srovnavat data hash
                $this->db->where('c_object_id', $object_id);
                $this->db->where('c_fuid', $fuid_hash);
                //$this->db->orderBy("c_rev","desc");
                $zaznam_data = $this->db->getOne('t_int_cache');
                if (!isset($zaznam_data['c_uid'])) {
                    $ret[] = 'v tabulce cache to neni';
                    // nemame vubec zaznam, provedeme insert
                    $insert_data = Array ("c_object_id" => $object_id,
                                   "c_fuid" => $fuid_hash,
                                   "c_json" => $json_data,
                                   "c_hash" => $data_hash
                    );
                    $id = $this->db->insert('t_int_cache', $insert_data);
                    if ($id) {
                        
                    }
                    else {
                        $ret[] = 'chyba insertu '.$this->db->getLastError();
                    }
                    // TODO nejake osetreni kdyz se nepovede vlozit. exception?
                }
                else {
                    $ret[] = 'v tabulce cache to je asi. '.print_r($zaznam_data, true);
                    // mame nejnovejsi zaznam v $zaznam_data
                    if ($zaznam_data['c_hash'] != $data_hash) {
                        // hash dat je jiny, vlozime to jako novy rev s novymi daty
                        $novy_rev = $zaznam_data['c_rev'] + 1;
                        $insert_data = Array ("c_object_id" => $object_id,
                                       "c_fuid" => $fuid_hash,
                                       "c_rev" => $novy_rev,
                                       "c_json" => $json_data,
                                       "c_hash" => $data_hash
                        );
                        $id = $this->db->insert ('t_int_cache', $insert_data);
                        // TODO nejake osetreni kdyz se nepovede vlozit. exception?
                    }
                    else {
                        // nedelame nic, data mame nactena a jsou stejna
                        // nebo treba jen zmenime datum ve stejnem rev?
                    }
                }
            }
            
            // vratime proste true
            return true;
            
        } catch (\Exception $e) {
            //$ret = get_class_methods($e);
            //$ret = $e->getErrors();
            return false;
        }
    }
    
}