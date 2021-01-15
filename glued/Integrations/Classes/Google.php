<?php

declare(strict_types=1);

namespace Glued\Integrations\Classes;

class Google
{

    protected $db;

/*
    public function __construct($db) {
        $this->db = $db;
    }
*/
    
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
    do $ret bych nacpal prurazeni
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
            foreach ($data_array as $sloupec) {
                // najdeme umisteni $sloupec v poli $nazvy_sloupcu
                $hledam_index = array_search($sloupec, $nazvy_sloupcu);
                if ($hledam_index === false) {
                    $finalne_vracime[$sloupec] = -1;
                }
                else {
                    $finalne_vracime[$sloupec] = $hledam_index;
                }
            }
            $ret = $finalne_vracime;
            
            return true;
        } catch (\Exception $e) {
            //$ret = get_class_methods($e);
            $ret = $e->getErrors();
            return false;
        }
    }
    
    
    
    
    /*
    uklada az ta dalsi funkce
        //      "sheets.rowcache": {       // php funkce, ktera cachene data do nasi tabulky - nejdriv udela ze vseho ve sloupecku A md5 a testne, ze jsou hashe fakt unikatni
        //         "meta": "Orig!A1:G1",
        //         "data": "Orig!A2:G5",
        //         "fuid": "A",
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
    
    
    
}