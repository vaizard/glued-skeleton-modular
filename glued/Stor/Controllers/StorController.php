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

class StorController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    
    
    
    // funkce co zpracuje poslany nahravany soubor
    // slouzi k ulozeni soubori do storu poslaneho POST
    // postupne to odbourame. zatim zruseno ze stor popupu ve stor browseru
    // je to pry jeste ve worklogu, TODO zrusit form z worklogu a pak tuto funkci
    // pripadne pokud tuto funkci zachovame, predelat na pouziti internal_upload nebo internal_create
    public function uploaderSave($request, $response)
    {
        $files = $request->getUploadedFiles();
        if (empty($files['file'])) {
            throw new Exception('Expected uploaded file, got none.');
        }
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        $newfile = $files['file'];
        
        $raw_path = $request->getParam('actual_dir');
        $upload_type = $request->getParam('upload_type');
        
        // vyjimka na my_files
        if ($raw_path == 'my_files') {
            $actual_dir = 'users';
            $actual_object = $GLOBALS['_GLUED']['authn']['user_id'];
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
        
        // pokud dir existuje v seznamu povolenych diru, uploadujem (ovsem je zadany timpadem i objekt)
        if (isset($app_dirs[$actual_dir])) {
            
            if ($newfile->getError() === UPLOAD_ERR_OK) {
                $filename = $newfile->getClientFilename();
                $sha512 = hash_file('sha512', $_FILES['file']['tmp_name']);
                
                // zjistime jestli soubor se stejnym hashem uz mame
                $this->db->where("c_sha512", $sha512);
                $this->db->getOne('t_stor_objects');
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
                    "c_user_id" => $GLOBALS['_GLUED']['authn']['user_id'],
                    "c_filename" => $filename,
                    "c_inherit_table" => $app_tables[$actual_dir],
                    "c_inherit_object" => $actual_object
                    );
                    $this->db->insert ('t_stor_links', $data);
                    
                    $this->flash->addMessage('info', 'Your file ('.$filename.') was uploaded successfully.');
                }
                else {
                    // soubor uz existuje v objects ale vlozime ho aspon do links
                    $data = Array (
                    "c_sha512" => $sha512,
                    "c_user_id" => $GLOBALS['_GLUED']['authn']['user_id'],
                    "c_filename" => $filename,
                    "c_inherit_table" => $app_tables[$actual_dir],
                    "c_inherit_object" => $actual_object
                    );
                    $this->db->insert ('t_stor_links', $data);
                    
                    $this->flash->addMessage('info', 'Your file ('.$filename.') was uploaded successfully as link. Its hash already exists in objects table.');
                }
            }
            else {
                $this->flash->addMessage('error', 'your file failed to upload.');
            }
        }
        else {
            $this->flash->addMessage('error', 'your cannot upload into this dir.');
        }
        
        if ($upload_type == 'browser') {
            $redirect_url = $this->routerParser->urlFor('stor.browser').'?filter=/'.$actual_dir.'/'.$actual_object;
        }
        else if ($upload_type == 'general') {   // obecny form nekde jinde mimo stor, posle si vlastni zpatecni adresu
            $redirect_url = $request->getParam('return_url');
        }
        else {
            // jinak tu byl uploader, ale ten uz nemame, takze to posleme na root browseru
            $redirect_url = $this->routerParser->urlFor('stor.browser');
        }
        
        return $response->withRedirect($redirect_url);
    }
    
    // zobrazovac nebo vynucovac stazeni
    public function serveFile($request, $response, $args)
    {
        // parametr id identifikuje link
        $link_id = $args['id'];
        
        // nacteme sha512
        $this->db->where ("c_uid", $link_id);
        $file_link = $this->db->getOne("t_stor_links");
        
        // nacteme mime
        $sloupce = array("c_json->>'$.data.mime' as mime", "c_json->>'$.data.storage[0].path' as path");
        $this->db->where("c_sha512", $file_link['c_sha512']);
        $file_data = $this->db->getOne("t_stor_objects", $sloupce);
        // select `c_json`->>'$.data.mime' as mime, `c_json`->>'$.data.storage[0].path' as path from t_stor_objects
        // 
        // path mame v takovem nejakem tvaru
        // ../private/stor/0/2/8/0
        $fullpath = $file_data['path'].'/'.$file_link['c_sha512'];
        
        header('Content-Type: '.$file_data['mime']);
        readfile($fullpath);    // taky vlastne nevim jestli to takto vypsat
        exit(); // ? nevim nevim
        
    }
    
    // update nazvu z popupoveho formu
    public function uploaderUpdate($request, $response)
    {
        $link_id = (int) $request->getParam('file_id');
        $actual_dir = $request->getParam('actual_dir');
        
        // nacteme si link
        $this->container->db->where("c_uid", $link_id);
        $link_data = $this->container->db->getOne('t_stor_links');
        if ($this->container->db->count == 0) { // TODO, asi misto countu pouzit nejaky test $link_data
            $this->container->flash->addMessage('error', 'pruser, soubor neexistuje, nevim na co jste klikli, ale jste tu spatne');
        }
        else {
            // pokud mame prava na tento objekt
            if ($this->container->permissions->have_action_on_object($link_data['c_inherit_table'], $link_data['c_inherit_object'], 'write')) {
                // zmenime nazev na novy
                $data = Array (
                    'c_filename' => $request->getParam('new_filename')
                );
                $this->container->db->where("c_uid", $link_id);
                if ($this->container->db->update('t_stor_links', $data)) {
                    $this->container->flash->addMessage('info', 'soubor byl prejmenovan');
                }
                else {
                    $this->container->flash->addMessage('error', 'prejmenovani se nepovedlo');
                }
            }
            else {
                $this->container->flash->addMessage('error', 'k prejmenovani nemate prava');
            }
        }
        
        // toto by melo byt vzdy nastaveno pri editaci, abychom mohli tu adresu zase vykreslit s uz zmenenym nazvem
        if (!empty($actual_dir)) {
            $redirect_url = $this->container->router->urlFor('stor.uploader').'/~/'.$actual_dir;
        }
        else {  // pro jistotu, kdyz to nebude nastaveno, jdeme na root
            $redirect_url = $this->container->router->urlFor('stor.uploader');
        }
        
        return $response->withRedirect($redirect_url);
    }
    
    // copy nebo move z modalu
    public function itemCopyMove($request, $response)
    {
        $link_id = (int) $request->getParam('file_id');
        $actual_dir = $request->getParam('actual_dir'); // jen v uploaderu (ten jeste existuje?)
        $action_type = $request->getParam('action_type');
        $target_dir = $request->getParam('target_dir');
        $target_object_id = $request->getParam('target_object_id');
        $set_new_owner = (int) $request->getParam('set_new_owner'); // 1 - system select, 2 - prihlaseny, 3 - nemenit
        $action_source = $request->getParam('action_source');   // jen v browseru (je to volane i odjinud?)
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        // nacteme si link
        $this->db->where("c_uid", $link_id);
        $link_data = $this->db->getOne('t_stor_links');
        if ($this->db->count == 0) { // TODO, asi misto countu pouzit nejaky test $link_data
            $this->flash->addMessage('error', 'pruser, soubor neexistuje, nevim na co jste klikli, ale jste tu spatne');
        }
        else {
            // nacteme prava na tabulku, TODO, meli bychom ale nacist prava na ten konkretni objekt, coz neni vyladene zatim
            //$allowed_global_actions = $this->container->permissions->read_global_privileges($link_data['c_inherit_table']);
            //$allowed_global_target_actions = $this->container->permissions->read_global_privileges($this->container->stor->app_tables[$target_dir]);
            
            // urceni ownera
            if ($set_new_owner == 1) {  // system select
                // pokud presunuju nebo kopiruju do private users, mel by byt owner vzdy ten user
                if ($target_dir == 'users') {
                    $new_owner = $target_object_id;
                }
                else {  // pokud je cil nejaky modul, tak u copy bych mel byt owner ja, a u move bud nemenit nebo ja
                    $new_owner = $GLOBALS['_GLUED']['authn']['user_id'];
                }
            }
            else if ($set_new_owner == 2) { $new_owner = $GLOBALS['_GLUED']['authn']['user_id']; }    // vzdy ja
            else if ($set_new_owner == 3) { $new_owner = $link_data['c_user_id']; }   // nemenit
            
            if ($action_type == 'copy') {
                //if (in_array('read', $allowed_global_actions) and in_array('write', $allowed_global_target_actions)) {
                    $data = Array (
                    "c_sha512" => $link_data['c_sha512'],
                    "c_user_id" => $new_owner,
                    "c_filename" => $link_data['c_filename'],
                    "c_inherit_table" => $app_tables[$target_dir],
                    "c_inherit_object" => $target_object_id
                    );
                    if ($this->db->insert ('t_stor_links', $data)) {
                        $this->flash->addMessage('info', 'soubor byl zkopirovan');
                    }
                    else {
                        $this->flash->addMessage('error', 'kopirovani se nepovedlo');
                    }
                /*
                }
                else {
                    $this->flash->addMessage('error', 'ke kopirovani nemate prava');
                }
                */
            }
            else if ($action_type == 'move') {
                //if (in_array('write', $allowed_global_actions) and in_array('write', $allowed_global_target_actions)) {
                    $data = Array (
                        'c_user_id' => $new_owner,
                        'c_inherit_table' => $app_tables[$target_dir],
                        'c_inherit_object' => $target_object_id
                    );
                    $this->db->where("c_uid", $link_id);
                    if ($this->db->update('t_stor_links', $data)) {
                        $this->flash->addMessage('info', 'soubor byl presunut');
                    }
                    else {
                        $this->flash->addMessage('error', 'presunuti se nepovedlo');
                    }
                /*
                }
                else {
                    $this->flash->addMessage('error', 'k presunu nemate prava');
                }
                */
            }
        }
        
        if ($action_source == 'browser') {
            $redirect_url = $this->routerParser->urlFor('stor.browser').'?filter=/'.$target_dir.'/'.$target_object_id;
        }
        else {
            // toto by melo byt vzdy nastaveno pri editaci, abychom mohli tu adresu zase vykreslit s uz zmenenym nazvem
            if (!empty($actual_dir)) {
                $redirect_url = $this->routerParser->urlFor('stor.uploader').'/~/'.$actual_dir;
            }
            else {  // pro jistotu, kdyz to nebude nastaveno, jdeme na root
                $redirect_url = $this->routerParser->urlFor('stor.uploader');
            }
        }
        
        return $response->withRedirect($redirect_url);
    }
    
    
    /* browser with a filter */
    
    // vypis stranky s filtracnim browserem souboru, select2
    public function storBrowserGui($request, $response, $args)
    {
        $vystup = '';
        $preset_options = '';
        
        $preset_filter = $request->getParam('filter');
        
        // nacteme si to z containeru ktery to ma ze tridy
        $app_dirs = $this->stor->app_dirs;
        $app_tables = $this->stor->app_tables;
        
        // pokud je v getu nejaky filter
        if (!empty($preset_filter)) {
            $casti_filtru = explode(' ', $preset_filter);
            foreach ($casti_filtru as $filter) {
                $safe_filter = trim($filter);
                if (!empty($filter)) {
                    $preset_options .= '<option value="'.$safe_filter.'" selected>'.$safe_filter.'</option>';
                }
            }
        }
        
        
        // priprava vyberu diru do copy move popupu
        $stor_dirs_options = '';
        foreach ($app_dirs as $dir => $description) {
            if ($dir == 'my_owned' or $dir == 'my_files') { continue; }
            $stor_dirs_options .= '<option value="'.$dir.'">'.$description.'</option>';
        }
        
        return $this->render($response, 'Stor/Views/stor-browser-gui.twig',
        array(
            'vystup' => $vystup,
            'preset_options' => $preset_options,
            'article_class' => 'items-list-page',
            'stor_dirs_options' => $stor_dirs_options,
            'ui_menu_active' => 'stor.browser'
        ));
    }
    
    
}

