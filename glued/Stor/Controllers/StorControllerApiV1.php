<?php

declare(strict_types=1);

namespace Glued\Stor\Controllers;

use Carbon\Carbon;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Spatie\Browsershot\Browsershot;

class StorControllerApiV1 extends AbstractTwigController
{
    
    // funkce co zpracuje poslany nahravany soubor jako api post request, vraci json
    public function uploaderApiSave($request, $response)
    {
        $files = $request->getUploadedFiles();
        
        // promenne, ktere se budou vracet
        $return_code = 0;
        $return_data = array();
        $return_message = '';
        $files_stored = 0;
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        $raw_path = $request->getParam('actual_dir');
        
        // vyjimka na my_files
        if ($raw_path == 'my_files') {
            $actual_dir = 'users';
            $actual_object = $_SESSION['core_user_id'];
        }
        else {
            $parts = explode('/', $raw_path);
            if (count($parts) > 1) {
                $actual_dir = $parts[0];
                $actual_object = $parts[1];
            }
            else {
                $actual_dir = '';   // pokud to neni objekt v diru, tak delame jako ze dir neexistuje.
            }
        }
        
        if (!empty($files['file']) and count($files['file']) > 0) {
            
            // pokud dir existuje v seznamu povolenych diru, uploadujem (ovsem je zadany timpadem i objekt)
            if (isset($app_dirs[$actual_dir])) {
                
                // $files['file'] je pole, s idexy 0 1 2 ... 
                // musime to projit vsechno
                $pocet_souboru = count($files['file']);
                
                foreach ($files['file'] as $file_index => $newfile) {
                    
                    //$newfile = $files['file'][0];
                    
                    if ($newfile->getError() === UPLOAD_ERR_OK) {
                        $filename = $newfile->getClientFilename();
                        
                        // ziskame tmp path ktere je privatni vlastnost $newfile, jeste zanorene v Stream, takze nejde normalne precist
                        // vypichneme si stream a pouzijeme na to reflection
                        $stream = $newfile->getStream();
                        $reflectionProperty = new \ReflectionProperty(\Nyholm\Psr7\Stream::class, 'uri');
                        $reflectionProperty->setAccessible(true);
                        $tmp_path = $reflectionProperty->getValue($stream);
                        
                        $sha512 = hash_file('sha512', $tmp_path);
                        
                        // zjistime jestli soubor se stejnym hashem uz mame
                        $this->db->where("c_sha512", $sha512);
                        $file_object = $this->db->getOne('t_stor_objects');
                        if ($this->db->count == 0) {
                            
                            // vytvorime tomu adresar
                            $dir1 = substr($sha512, 0, 1);
                            $dir2 = substr($sha512, 1, 1);
                            $dir3 = substr($sha512, 2, 1);
                            $dir4 = substr($sha512, 3, 1);
                            
                            $cilovy_dir = __ROOT__.'/private/data/stor/'.$dir1.'/'.$dir2.'/'.$dir3.'/'.$dir4;
                            
                            if (!is_dir($cilovy_dir)) { mkdir($cilovy_dir, 0777, true); }
                            
                            // presuneme
                            // $full_path = "/var/www/html/glued/private/";
                            $newfile->moveTo($cilovy_dir.'/'.$sha512);
                            
                            // pokud ne, vlozime
                            $new_file_array = array();
                            $new_file_array['_v'] = '1';
                            $new_file_array['sha512'] = $sha512;
                            $new_file_array['size'] = $newfile->getSize();
                            $new_file_array['mime'] = $newfile->getClientMediaType();
                            $new_file_array['checked'] = false;
                            $new_file_array['ts_created'] = time();
                            $new_file_array['storage'] = array(array("driver" => "fs", "path" => $cilovy_dir));
                            
                            $new_data_array = array();
                            $new_data_array['data'] = $new_file_array;
                            
                            $json_string = json_encode($new_data_array);
                            
                            // pozor, spojit dve vkladani pres commit, TODO
                            
                            // vlozime do objects
                            $data = Array ("c_json" => $json_string);
                            $this->db->insert ('t_stor_objects', $data);
                            
                            // vlozime do links
                            $data = Array (
                            "c_sha512" => $sha512,
                            "c_user_id" => $_SESSION['core_user_id'],
                            "c_filename" => $filename,
                            "c_inherit_table" => $app_tables[$actual_dir],
                            "c_inherit_object" => $actual_object
                            );
                            $new_id = $this->db->insert ('t_stor_links', $data);
                            
                            $this->flash->addMessage('info', 'Your file ('.$filename.') was uploaded successfully.');
                            
                            // priprava navratovych dat
                            $return_data[$file_index]['link-id'] = $new_id;
                            $return_data[$file_index]['name'] = $filename;
                            $return_data[$file_index]['module-name'] = $actual_dir;
                            $return_data[$file_index]['object-id'] = $sha512;
                            $return_data[$file_index]['link'] = $this->routerParser->urlFor('stor.serve.file', ['id' => $new_id, 'filename' => $filename]);
                            $return_data[$file_index]['size'] = $new_file_array['size'];
                            $return_data[$file_index]['mime-type'] = $new_file_array['mime'];
                        }
                        else {
                            // soubor uz existuje v objects ale vlozime ho aspon do links
                            $data = Array (
                            "c_sha512" => $sha512,
                            "c_user_id" => $_SESSION['core_user_id'],
                            "c_filename" => $filename,
                            "c_inherit_table" => $app_tables[$actual_dir],
                            "c_inherit_object" => $actual_object
                            );
                            $new_id = $this->db->insert ('t_stor_links', $data);
                            
                            $this->flash->addMessage('info', 'Your file ('.$filename.') was uploaded successfully as link. Its hash already exists in objects table.');
                            
                            // priprava navratovych dat
                            $file_data = json_decode($file_object['c_json'], true);
                            
                            $return_data[$file_index]['link-id'] = $new_id;
                            $return_data[$file_index]['name'] = $filename;
                            $return_data[$file_index]['module-name'] = $actual_dir;
                            $return_data[$file_index]['object-id'] = $sha512;
                            $return_data[$file_index]['link'] = $this->routerParser->urlFor('stor.serve.file', ['id' => $new_id, 'filename' => $filename]);
                            $return_data[$file_index]['size'] = $file_data['data']['size'];
                            $return_data[$file_index]['mime-type'] = $file_data['data']['mime'];
                        }
                        
                        $files_stored++;
                    }
                }   // konec cyklu pres nahrane soubory
                
                if ($files_stored > 0) {
                    $return_message = 'Upload successful ('.$files_stored.')files stored.';
                    $return_code = 200;
                }
                else {
                    $this->flash->addMessage('error', 'your file failed to upload.');
                    $return_message = 'your file failed to upload.';
                    $return_code = 500;
                }
            }
            else {
                $this->flash->addMessage('error', 'your cannot upload into this dir.');
                $return_message = 'your cannot upload into this dir.';
                $return_code = 500;
            }
        }
        else {
            $this->flash->addMessage('error', 'Expected uploaded file, got none.');
            $return_message = 'Expected uploaded file, got none.';
            $return_code = 500;
        }
        
        // vybuildime json response
        $builder = new JsonResponseBuilder('stor/upload', 1);
        $payload = $builder->withData($return_data)->withMessage($return_message)->withCode($return_code)->build();
        return $response->withJson($payload);
    }
    
    // funkce, ktera vraci prvni radek s dvojteckou, patri do browseru
    // davam to do samostatne funkce, protoze to bude pouzite 4x v showFilteredFiles a bude to tak prehlednejsi
    private function firstRowUplinkBrowser($dataID, $dataText) {
        return '
                        <tr role="row" class="odd">
                            <td class="col-sm-1"><i class="fa fa-folder"></i></td>
                            <td class="col-sm-3">
                                <a href="" class="stor-shortcuts" data-id="'.$dataID.'" data-text="'.$dataText.'">
                                    <b class="item-title"> .. </b>
                                </a>
                            </td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2 d-none d-sm-table-cell"></td>
                            <td class="col-sm-2"></td>
                        </tr>
            ';
        
        /*
                        <li class="item">
                            <div class="item-row">
                                <div class="item-col fixed">
                                    <i class="fa fa-folder-open-o fa-2x"></i>
                                </div>
                                <div class="item-col fixed pull-left item-col-title">
                                    <div class="item-heading">Name</div>
                                    <div>
                                        <a href="" class="stor-shortcuts" data-id="'.$dataID.'" data-text="'.$dataText.'">
                                            <h4 class="item-title"> .. </h4>
                                        </a>
                                    </div>
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col item-col-date">
                                </div>
                                <div class="item-col fixed item-col-actions-dropdown">
                                </div>
                            </div>
                        </li>
        */
    }
    
    // prehled odpovidajicich objektu do modal popupu pro kopirovani
    public function showModalObjects($request, $response) {
        $vystup = '';
        
        $dirname = $request->getParam('dirname');
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        if (isset($app_dirs[$dirname])) {
            if (isset($app_tables[$dirname])) {
                // nacteme idecka
                $cols = Array("c_uid");
                $this->db->orderBy("c_uid","asc");
                $idecka = $this->db->get($app_tables[$dirname], null, $cols);
                if ($this->db->count > 0) {
                    foreach ($idecka as $idecko) {
                        $vystup .= '<option value="'.$idecko['c_uid'].'">'.$idecko['c_uid'].' - nazev</option>';
                    }
                }
            }
        }
        
        // protoze je to ajax, tak vystup nebudeme strkat do view ale rovnou ho vytiskneme
        
        $response->getBody()->write($vystup);
        return $response;
    }
    
    // vraci moznosti do select2 podle toho co je zatim napsane. vystup je v jsonu, pole objektu
    public function showFilterOptions($request, $response) {
        $vystup = '';
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        $term = $request->getParam('term');
        
        // podle toho cim to zacne
        // lomitko = adresar, @ = uzivatel, # = tag
        $prvni_znak = mb_substr($term, 0, 1, 'utf-8');
        
        if ($term == '//') {
            $vystup .= '
    {
      "results": [
        {
          "id": "//",
          "text": "//apps"
        }
      ]
    }
            ';
        }
        else if ($prvni_znak == '/') {   // adresare
            $casti = explode('/', $term);
            $app_cast = $casti[1];
            // zjistime kolika odpovida apps
            $objekty_adresaru = array();
            $posledni_dir = '';
            foreach ($app_dirs as $dir => $description) {
                if (!isset($app_tables[$dir])) { continue; }
                if (empty($app_cast) or substr_count($dir, $app_cast) > 0) {
                    $objekty_adresaru[] = '
        {
          "id": "/'.$dir.'",
          "text": "/'.$dir.'"
        }
                    ';
                    $posledni_dir = $dir;
                }
            }
            
            $vystup .= '
{
  "results": [
            ';
            if (isset($casti[2]) and count($objekty_adresaru) == 1) { // je tam druhe lomitko, nabidneme teda objekty
                $objekt_cast = $casti[2];
                //$cols = Array("c_uid", "stor_name");
                $cols = Array("c_uid");
                $this->db->orderBy("c_uid","asc");
                $idecka = $this->db->get($app_tables[$posledni_dir], null, $cols);
                if ($this->db->count > 0) {
                    $objekty_modulu = array();
                    foreach ($idecka as $idecko) {
                        //$nazev = $idecko['c_uid'].' - '.$idecko['stor_name'];
                        $nazev = $idecko['c_uid'];
                        if (empty($objekt_cast) or substr_count(mb_strtolower($nazev, 'utf-8'), mb_strtolower($objekt_cast, 'utf-8')) > 0) {
                            $objekty_modulu[] = '
            {
              "id": "/'.$posledni_dir.'/'.$idecko['c_uid'].'",
              "text": "/'.$posledni_dir.'/'.$nazev.'"
            }
                            ';
                        }
                    }
                    $vystup .= implode(',', $objekty_modulu);
                }
            }
            else {  // je tam jen jedno lomitko, nabidneme appy
                $vystup .= implode(',', $objekty_adresaru);
            }
            $vystup .= '
  ]
}
            ';
        }
        else if ($prvni_znak == '@') {  // uzivatele
            // nacteme idecka
            $cols = Array("c_uid", "c_name");
            $this->db->orderBy("c_uid","asc");
            $idecka = $this->db->get('t_core_users', null, $cols);
            $objekty_useru = array();
            if ($this->db->count > 0) {
                foreach ($idecka as $idecko) {
                    $objekty_useru[] = '
    {
      "id": "@'.$idecko['c_uid'].'",
      "text": "@'.$idecko['c_uid'].' - '.$idecko['c_name'].'"
    }
                    ';
                }
            }
            $vystup .= '
    {
      "results": [';
        $vystup .= implode(',', $objekty_useru);
        $vystup .= '
      ]
    }
            ';
        }
        else if ($prvni_znak == '#') {  // tagy, zatim nemame tabulku
            $vystup .= '
    {
      "results": [
        {
          "id": "#tag1",
          "text": "#tag1"
        },
        {
          "id": "#tag1",
          "text": "#tag2"
        }
      ]
    }
            ';
        }
        else {  // musime vratit prazdne pole, aby to bylo validni
            $vystup .= '
    {
      "results": [ ]
    }
            ';
        }
        
        // protoze je to ajax, tak vystup nebudeme strkat do view ale rovnou ho vytiskneme
        
        $response->getBody()->write($vystup);
        return $response;
    }
    
    
    // fukce co vypise prehled filtrovanych souboru v adresari
    public function showFilteredFiles($request, $response)
    {
        $vystup = '';
        $uploader = '';
        $bude_uploader = false;
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        $raw_filters = $request->getParam('filters');
        $orderby = $request->getParam('orderby');
        $direction = $request->getParam('direction');
        $page = $request->getParam('page');
        
        // dekodujeme na pole
        $filters = json_decode($raw_filters, true);
        
        // vrsek vzdy
        // kvuli tomu, ze tu mame dropdown, ktery muze sahat mimo tabulku, dame tam defaultni overflow-x: visible; ktere prebije csskove auto
        // a min width ma responsive table 800px ale to je tady zbytecne moc. prebijeme to 400px
        $vystup .= '<div class="card">';
        $vystup .= '
        <div class="card-body">
          <div class="table-responsive" style="overflow-x: visible;">
            <table class="table table-sm table-hover" style="min-width: 400px;">';
        
        // header tabulky
        $vystup .= '
              <thead>
                <tr>
                  <th class="col-sm-1">Type</th>
                  <th class="col-sm-3">Name</th>
                  <th class="col-sm-2">Size</th>
                  <th class="col-sm-2">App</th>
                  <th class="col-sm-2 d-none d-sm-table-cell">Uploaded</th>
                  <th class="col-sm-2">Actions</th>
                </tr>
              </thead>
              <tbody>
        ';
        
        /*
                        <li class="item item-list-header">
                            <div class="item-row">
                                <div class="item-col item-col-header fixed">
                                    <div>
                                        <span>Type</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header item-col-title" onclick="filter_stor_files(\'name\', \''.(($orderby == 'name' and $direction == 'asc')?'desc':'asc').'\', 1);">
                                    <div>
                                        <span><i class="fa fa-sort"></i> Name</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header" onclick="filter_stor_files(\'size\', \''.(($orderby == 'size' and $direction == 'asc')?'desc':'asc').'\', 1);">
                                    <div>
                                        <span><i class="fa fa-sort"></i> Size</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header">
                                    <div class="no-overflow">
                                        <span>App</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header">
                                    <div class="no-overflow">
                                        <span>Owner</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header item-col-date" onclick="filter_stor_files(\'uploaded\', \''.(($orderby == 'uploaded' and $direction == 'asc')?'desc':'asc').'\', 1);">
                                    <div>
                                        <span><i class="fa fa-sort"></i> Uploaded</span>
                                    </div>
                                </div>
                                <div class="item-col item-col-header fixed item-col-actions-dropdown"> </div>
                            </div>
                        </li>
        */
        
        
        // zanalyzujeme si co mame zadane ($filters)
        if (count($filters) > 0) {
            $je_tam_apps = false;
            $jsou_tam_objekty = false;
            $objektovy_dir = '';
            $pole_adresaru = array();
            $pole_useru = array();
            $pole_tagu = array();
            $pole_nazvu = array();
            
            
            $uploader_path = '';
            
            foreach ($filters as $filter) {
                
                //$vystup .= '<li class="item">zpracovavam filter: '.$filter.'</li>';
                
                // podle toho cim to zacne
                // lomitko = adresar, @ = uzivatel, # = tag
                $prvni_znak = mb_substr($filter, 0, 1, 'utf-8');
                
                // nejdriv detekujeme pritomnost apps //
                if ($filter == '//') {
                    $je_tam_apps = true;
                }
                // lomitkovy filtr by mel byt jen jeden, pokud bude vic, vezmeme jen prvni (a nebo to muzeme vzit jako or?)
                else if ($prvni_znak == '/') {
                    $pole_adresaru[] = $filter; // dame to tam cele, protoze pak to teprve budeme delit na lomitka a muze tam byt i objekt
                    // zjistime, jestli to nahodou neni pozadavek na vypis objektu z app
                    foreach ($app_dirs as $dir => $description) {
                        if (!isset($app_tables[$dir])) { continue; }
                        if ($filter == '/'.$dir.'/') {
                            $jsou_tam_objekty = true;
                            $objektovy_dir = $dir;
                        }
                    }
                    //$vystup .= '<li class="item">objektovy dir: '.$objektovy_dir.'</li>';
                }
                // ownerovy filtr muze byt taky jen jeden, taky vemem jen prvni (a nebo to muzeme vzit jako or?)
                else if ($prvni_znak == '@') {
                    $pole_useru[] = mb_substr($filter, 1, null, 'utf-8');
                }
                // tagovych filtru muze byt vic
                else if ($prvni_znak == '#') {
                    $pole_tagu[] = mb_substr($filter, 1, null, 'utf-8');
                }
                // nazvovych filtru muze byt vic
                else {
                    $pole_nazvu[] = $filter;
                }
            }
            
            // pokud je tam apps, vypiseme jen apps
            if ($je_tam_apps) {
                // zpet do rootu
                $vystup .= $this->firstRowUplinkBrowser('', '');
                foreach ($app_dirs as $dir => $description) {
                    if (!isset($app_tables[$dir])) { continue; }
                    $vystup .= '
                        <tr role="row" class="odd">
                            <td class="col-sm-1"><i class="fa fa-folder"></i></td>
                            <td class="col-sm-3">
                                <a href="" class="stor-shortcuts" data-id="/'.$dir.'/" data-text="/'.$dir.'/">
                                    <b class="item-title">/'.$dir.'/</b>
                                </a>
                            </td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2 d-none d-sm-table-cell"></td>
                            <td class="col-sm-2"></td>
                        </tr>
                    ';
                    /*
                        <li class="item">
                            <div class="item-row">
                                <div class="item-col fixed">
                                    <i class="fa fa-folder-o fa-2x"></i>
                                </div>
                                <div class="item-col fixed pull-left item-col-title">
                                    <div class="item-heading">Name</div>
                                    <div>
                                        <a href="" class="stor-shortcuts" data-id="/'.$dir.'/" data-text="/'.$dir.'/">
                                            <h4 class="item-title">/'.$dir.'/</h4>
                                        </a>
                                    </div>
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col">
                                </div>
                                <div class="item-col item-col-date">
                                </div>
                                <div class="item-col fixed item-col-actions-dropdown">
                                </div>
                            </div>
                        </li>
                    */
                }
            }
            else if ($jsou_tam_objekty) {   // to znamena ze jsme v jedne app a vypisujeme jeji objekty
                // nejdriv zpet do app
                $vystup .= $this->firstRowUplinkBrowser('//', '//apps');
                // nacteme idecka
                //$cols = Array("c_uid", "stor_name");
                $cols = Array("c_uid");
                $this->db->orderBy("c_uid","asc");
                $idecka = $this->db->get($app_tables[$objektovy_dir], null, $cols);
                if ($this->db->count > 0) {
                    foreach ($idecka as $idecko) {
                        // '.$idecko['stor_name'].'
                        $vystup .= '
                        <tr role="row" class="odd">
                            <td class="col-sm-1"><i class="fa fa-folder"></i></td>
                            <td class="col-sm-3">
                                <a href="" class="stor-shortcuts" data-id="/'.$objektovy_dir.'/'.$idecko['c_uid'].'" data-text="/'.$objektovy_dir.'/'.$idecko['c_uid'].' - pfff">
                                    <b class="item-title"> '.$idecko['c_uid'].' - nazev </b>
                                </a>
                            </td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2 d-none d-sm-table-cell"></td>
                            <td class="col-sm-2"></td>
                        </tr>
                        ';
                        
                        /*
                                    <li class="item">
                                        <div class="item-row">
                                            <div class="item-col fixed">
                                                <i class="fa fa-folder-o fa-2x"></i>
                                            </div>
                                            <div class="item-col fixed pull-left item-col-title">
                                                <div class="item-heading">Name</div>
                                                <div>

                                                </div>
                                            </div>
                                            <div class="item-col">
                                            </div>
                                            <div class="item-col">
                                            </div>
                                            <div class="item-col">
                                            </div>
                                            <div class="item-col item-col-date">
                                            </div>
                                            <div class="item-col fixed item-col-actions-dropdown">
                                            </div>
                                        </div>
                                    </li>
                        */
                    }
                }
            }
            else {  // vypis souboru v objektu nebo obecny vypis souboru
                
                // krome tagovych muzeme udelat vyber jednim sql dotazem nad links tabulkou
                $sloupce = array("lin.c_uid", "lin.c_user_id", "lin.c_filename", "lin.c_inherit_table", "lin.c_inherit_object", "lin.c_ts_created", "obj.c_sha512", "obj.c_json ->>'$.data.size' as size", "obj.c_json ->>'$.data.mime' as mime");
                $this->db->join("t_stor_objects obj", "obj.c_sha512=lin.c_sha512", "LEFT");
                if (count($pole_adresaru) > 0) {
                    $casti = explode('/', $pole_adresaru[0]);
                    $inherit_table = $app_tables[$casti[1]];
                    $this->db->where("c_inherit_table", $inherit_table);
                    if (!empty($casti[2])) {
                        $this->db->where("c_inherit_object", $casti[2]);
                        // je tam adresar i objekt, muzeme ukazat uploadovaci form
                        $bude_uploader = true;
                        $uploader_path = $casti[1].'/'.$casti[2];
                    }
                    // jsme v lomitkovem filtru, takze mame nejakou app, pridame prvni radek ktery do ni povede zpet
                    $vystup .= $this->firstRowUplinkBrowser('/'.$casti[1].'/', '/'.$casti[1].'/');
                }
                if (count($pole_useru) > 0) {
                    $this->db->where("c_user_id", $pole_useru[0]);
                }
                if (count($pole_tagu) > 0) {
                    // tagy zatim nemame
                }
                if (count($pole_nazvu) > 0) {
                    $this->db->where("c_filename", '%'.$pole_nazvu[0].'%', 'like');
                }
                // oderby
                if ($orderby == 'name') {
                    $this->db->orderBy("c_filename", $direction);
                }
                else if ($orderby == 'size') {
                    $this->db->orderBy("size", $direction);
                }
                else if ($orderby == 'uploaded') {
                    $this->db->orderBy("c_ts_created", $direction);
                }
                
                $files = $this->db->get('t_stor_links lin', null, $sloupce);
                if (count($files) > 0) {
                    foreach ($files as $data) {
                        $dir_path = array_search($data['c_inherit_table'], $app_tables);
                        $full_path = $dir_path.'/'.$data['c_inherit_object'];
                        
                        // urcime jake mame globalni prava na inherit objekt (ne na soubor ale na objekt ve kterem soubor je)
                        // TODO, pole s nactenymi pravy by melo byt globalni, aby se necetly stejna prava u kazdeho dalsiho souboru se stejnou dvojici table objekt
                        $allowed_global_actions = array();
                        //if ($this->container->permissions->have_action_on_object($data['c_inherit_table'], $data['c_inherit_object'], 'list')) { $allowed_global_actions[] = 'list'; }
                        //if ($this->container->permissions->have_action_on_object($data['c_inherit_table'], $data['c_inherit_object'], 'read')) { $allowed_global_actions[] = 'read'; }
                        //if ($this->container->permissions->have_action_on_object($data['c_inherit_table'], $data['c_inherit_object'], 'write')) { $allowed_global_actions[] = 'write'; }
                        
                        $allowed_global_actions[] = 'list';
                        $allowed_global_actions[] = 'read';
                        $allowed_global_actions[] = 'write';
                        
                        // jestli soubor vubec vylistovat, TODO tohle je ale spis read pravo na objekt. ne list.
                        if (in_array('list', $allowed_global_actions)) {
                            
                            $action_dropdown = '';
                            // jestli bude ozubene kolo
                            if (in_array('write', $allowed_global_actions)) {
                                $action_dropdown = '
                                    <div class="dropdown">
                                        <div class="btn-group dropleft">
                                          <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                          </button>
                                          <div class="dropdown-menu dropleft" x-placement="left-start" style="background-color: #cdd3d8; font-size: 12px;">
                                                <button class="dropdown-item" type="button" data-toggle="modal" data-target="#confirm-modal" onclick="$(\'#delete_file_uid\').val('.$data['c_uid'].');"><i class="fa fa-trash-o "></i> Delete</button>
                                                <button class="dropdown-item" type="button" data-toggle="modal" data-target="#modal-edit-stor" onclick="$(\'#edit_file_uid\').val('.$data['c_uid'].');var pomucka = $(\'#fname_'.$data['c_uid'].'\').text(); $(\'#edit_file_fname\').val(pomucka);"><i class="fa fa-pencil"></i> Edit</button>
                                                <button class="dropdown-item" type="button" data-toggle="modal" data-target="#modal-copy-move-stor" onclick="$(\'#copy_move_file_uid\').val('.$data['c_uid'].');"><i class="fa fa-files-o"></i> Copy/Move</button>
                                          </div>
                                        </div>
                                    </div>
                                ';
                            }
                            
                            // <i class="fa '.$this->container->stor->font_awesome_mime_icon($data['mime']).' fa-2x"></i>
                            // '.$this->container->auth->user_screenname($data['c_owner']).'
                            $vystup .= '
                                <tr role="row" class="odd">
                                    <td class="col-sm-1"><i class="fa fa-file"></i></td>
                                    <td class="col-sm-3">
                                        '.(in_array('read', $allowed_global_actions)?'
                                        <a href="'.$this->routerParser->urlFor('stor.serve.file', ['id' => $data['c_uid'], 'filename' => $data['c_filename']]).'" class="">
                                            <b id="fname_'.$data['c_uid'].'" class="item-title">'.$data['c_filename'].'</b>
                                        </a>
                                        ':'
                                            <b id="fname_'.$data['c_uid'].'" class="item-title">'.$data['c_filename'].'</b>
                                        ').'
                                    </td>
                                    <td class="col-sm-2">'.$this->stor->human_readable_size($data['size']).'</td>
                                    <td class="col-sm-2">
                                        <a href="" class="stor-shortcuts" data-id="/'.$full_path.'" data-text="/'.$full_path.'">
                                            /'.$full_path.'
                                        </a>
                                    </td>
                                    <td class="col-sm-2 d-none d-sm-table-cell">'.$data['c_ts_created'].'</td>
                                    <td class="col-sm-2">'.$action_dropdown.'</td>
                                </tr>
                            ';
                            /*
                                <li class="item">
                                    <div class="item-row">
                                        <div class="item-col fixed">
                                            
                                        </div>
                                        <div class="item-col fixed pull-left item-col-title">
                                            <div class="item-heading">Name</div>
                                            <div>
                                                '.(in_array('read', $allowed_global_actions)?'
                                                <a href="'.$this->routerParser->urlFor('stor.serve.file', ['id' => $data['c_uid'], 'filename' => $data['c_filename']]).'" class="">
                                                    <h4 id="fname_'.$data['c_uid'].'" class="item-title">'.$data['c_filename'].'</h4>
                                                </a>
                                                ':'
                                                    <h4 id="fname_'.$data['c_uid'].'" class="item-title">'.$data['c_filename'].'</h4>
                                                ').'
                                            </div>
                                        </div>
                                        <div class="item-col">
                                            <div class="item-heading">Sales</div>
                                            <div>  </div>
                                        </div>
                                        <div class="item-col">
                                            <div class="item-heading">Category</div>
                                            <div class="no-overflow">
                                                <a href="" class="stor-shortcuts" data-id="/'.$full_path.'" data-text="/'.$full_path.'">
                                                    /'.$full_path.'
                                                </a>
                                            </div>
                                        </div>
                                        <div class="item-col">
                                            <div class="item-heading">Owner</div>
                                            <div class="no-overflow">
                                                <a href=""></a>
                                            </div>
                                        </div>
                                        <div class="item-col item-col-date">
                                            <div class="item-heading">Uploaded</div>
                                            <div class="no-overflow"> '.$data['c_ts_created'].' </div>
                                        </div>
                                        <div class="item-col fixed item-col-actions-dropdown">
                                            '.$action_dropdown.'
                                        </div>
                                    </div>
                                </li>
                            */
                        }
                    }
                    
                    // na konec pridame jeden prazdny radek
                    /*
                    $vystup .= '
                        <tr role="row" class="odd">
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2">
                                <div style="height: 150px;">nic</div>
                            </td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2">
                            </td>
                            <td class="col-sm-2"></td>
                            <td class="col-sm-2"></td>
                        </tr>
                    ';
                    */
                    
                }
            }
        }
        else {  // jsme v zakladnim vyberu my files a app
            //$your_user_id = $this->container->auth_user->user_id;
            $your_user_id = $_SESSION['core_user_id'];
            //$your_screenname = $this->container->auth->user_screenname($your_user_id);
            $your_screenname = 'noob';
            
            $vystup .= '
                <tr role="row" class="odd">
                    <td class="col-sm-1"><i class="fa fa-folder"></i></td>
                    <td class="col-sm-3">
                        <a href="" class="stor-shortcuts" data-id="@'.$your_user_id.'" data-text="@'.$your_user_id.' - '.$your_screenname.'">
                            <b class="item-title">Files I created</b>
                        </a>
                    </td>
                    <td class="col-sm-2"></td>
                    <td class="col-sm-2"></td>
                    <td class="col-sm-2 d-none d-sm-table-cell"></td>
                    <td class="col-sm-2"></td>
                </tr>
                
                <tr role="row" class="odd">
                    <td class="col-sm-1"><i class="fa fa-folder"></i></td>
                    <td class="col-sm-3">
                        <a href="" class="stor-shortcuts" data-id="//" data-text="//apps">
                            <b class="item-title">Apps</b>
                        </a>
                    </td>
                    <td class="col-sm-2"></td>
                    <td class="col-sm-2"></td>
                    <td class="col-sm-2 d-none d-sm-table-cell"></td>
                    <td class="col-sm-2"></td>
                </tr>
            ';
            
            /*
            $vystup .= '
                <li class="item">
                    <div class="item-row">
                        <div class="item-col fixed">
                            <i class="fa fa-folder-o fa-2x"></i>
                        </div>
                        <div class="item-col fixed pull-left item-col-title">
                            <div class="item-heading">Name</div>
                            <div>
                                <a href="" class="stor-shortcuts" data-id="@'.$your_user_id.'" data-text="@'.$your_user_id.' - '.$your_screenname.'">
                                    <h4 class="item-title">My files</h4>
                                </a>
                            </div>
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col item-col-date">
                        </div>
                        <div class="item-col fixed item-col-actions-dropdown">
                        </div>
                    </div>
                </li>
                <li class="item">
                    <div class="item-row">
                        <div class="item-col fixed">
                            <i class="fa fa-folder-o fa-2x"></i>
                        </div>
                        <div class="item-col fixed pull-left item-col-title">
                            <div class="item-heading">Name</div>
                            <div>
                                <a href="" class="stor-shortcuts" data-id="//" data-text="//apps">
                                    <h4 class="item-title">Apps</h4>
                                </a>
                            </div>
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col">
                        </div>
                        <div class="item-col item-col-date">
                        </div>
                        <div class="item-col fixed item-col-actions-dropdown">
                        </div>
                    </div>
                </li>
            ';
            */
        }
        
        $vystup .= '</tbody>';
        $vystup .= '</table>
          </div>
        </div>';
        $vystup .= '</div>';    // .card
        
        // debug
        $vystup .= '<div class="alert alert-info" role="alert">filtrovaci json: '.$raw_filters.', orderby: '.$orderby.', direction: '.$direction.', page: '.$page.'</div>';
        
        
        // textovy vystup ajaxu nejdriv vyrenderujeme pres view, aby se tam dosadilo csrf pres middleware
        if ($bude_uploader) {
            return $this->render($response, 'Stor/Views/partials/filter-with-upload.twig',
            array(
                'vystup' => $vystup,
                'uploader_path' => $uploader_path
            ));
        }
        else {
            return $this->render($response, 'Stor/Views/partials/filter-without-upload.twig',
            array(
                'vystup' => $vystup
            ));
        }
    }
    
    // mazani ajaxem
    public function ajaxDelete($request, $response) {
        $vystup = '';
        
        $link_id = $request->getParam('link_id');
        
        // TODO zjistit jestli mame write prava na inherit table a objekt
        
        
        $returned_data = $this->stor->delete_stor_file($link_id);
        
        // protoze je to ajax, tak vystup nebudeme strkat do view ale rovnou ho vytiskneme
        
        $response->getBody()->write($returned_data['message']);
        return $response;
    }
    
    // prejmenovani ajaxem. nebudeme na to delat zvlastni funkci ve tride, je to jednoduche
    public function ajaxUpdate($request, $response) {
        $vystup = '';
        
        $link_id = $request->getParam('link_id');
        $new_fname = $request->getParam('new_fname');
        
        // nacteme si link
        $this->db->where("c_uid", $link_id);
        $link_data = $this->db->getOne('t_stor_links');
        if ($this->db->count == 0) { // TODO, asi misto countu pouzit nejaky test $link_data
            $vystup = 'pruser, soubor neexistuje, nevim na co jste klikli, ale jste tu spatne';
        }
        else {
            // pokud mame prava na tento objekt, TODO
            
            // zmenime nazev na novy
            $data = Array (
                'c_filename' => $new_fname
            );
            $this->db->where("c_uid", $link_id);
            if ($this->db->update('t_stor_links', $data)) {
                $vystup = 'soubor byl prejmenovan';
            }
            else {
                $vystup = 'prejmenovani se nepovedlo';
            }
            
            // byvale prava
            /*
            if ($this->container->permissions->have_action_on_object($link_data['c_inherit_table'], $link_data['c_inherit_object'], 'write')) {
                
            }
            else {
                $vystup = 'k prejmenovani nemate prava';
            }
            */
        }
        
        // protoze je to ajax, tak vystup nebudeme strkat do view ale rovnou ho vytiskneme
        
        $response->getBody()->write($vystup);
        return $response;
    }
    
    // jednoduchy vypis souboru ve storu pro dany objekt (tabulka, id radku), pouzite ve funkci, ktera vypisuje soubory v ruznych jinych modulech
    // TODO ted neni vyuzite, ale budeme to zrejme potrebovat a tak si to tu zatim necham. pokud bychom to udelali jinak, tak smazat
    public function ajaxListFilesBasic($request, $response) {
        $vystup_souboru = '';
        $mame_id = false;
        
        $raw_dirname = $request->getParam('dirname');
        
        // musi tam byt id
        $dily = explode('/', $raw_dirname);
        $dirname = $dily[0];
        if (count($dily) > 1) {
            $object_id = $dily[1];
            $mame_id = true;
        }
        
        if ($mame_id and isset($this->container->stor->app_dirs[$dirname])) {
            // prava, TODO, jako v showFiles
            
            $object_tabulka = $this->container->stor->app_tables[$dirname];
            
            $sloupce = array("lin.c_uid", "lin.c_owner", "lin.c_filename", "obj.sha512", "obj.doc->>'$.data.size' as size", "obj.doc->>'$.data.mime' as mime", "obj.doc->>'$.data.ts_created' as ts_created");
            $this->container->db->join("t_stor_objects obj", "obj.sha512=lin.c_sha512", "LEFT");
            $this->container->db->where("c_inherit_table", $object_tabulka);
            $this->container->db->where("c_inherit_object", $object_id);
            $this->container->db->orderBy("lin.c_filename","asc");
            $files = $this->container->db->get('t_stor_links lin', null, $sloupce);
            if (count($files) > 0) {
                foreach ($files as $filedata) {
                    $adresa = $this->container->router->pathFor('stor.serve.file', ['id' => $filedata['c_uid'], 'filename' => $filedata['c_filename']]);
                    $vystup_souboru .= '
                    <div>
                        <a href="'.$adresa.'" class="">
                            <br />
                            '.$filedata['c_filename'].'
                            <a class="remove" href="#" data-toggle="modal" data-target="#confirm-modal" onclick="$(\'#delete_file_uid\').val('.$filedata['c_uid'].');">
                                <i class="fa fa-trash-o "></i>
                            </a>
                        </a>
                    </div>
                    ';
                }
            }
            else {
                $vystup_souboru .= '<div>no files uploaded</div>';
            }
        }
        else {
            $vystup_souboru .= '<div>bad syntax</div>';
        }
        
        // protoze je to ajax, tak vystup nebudeme strkat do view ale rovnou ho vytiskneme
        
        $response->getBody()->write($vystup_souboru);
        return $response;
    }
    
    
}
