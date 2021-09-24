<?php

declare(strict_types=1);

namespace Glued\Social\Controllers;

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

class SocialController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */

    public function fb_profile(Request $request, Response $response): Response
    {
        $domains = $this->db->get('t_core_domains');
        $user_id = $GLOBALS['_GLUED']['authn']['user_id'] ?? false;
        $token = '';
        
        $this->db->where("c_user_id", $user_id);
        $this->db->where("c_service", 'fb');
        $this->db->where("c_type", 'user');
        $token_data = $this->db->getOne('t_social_tokens');
        if (!empty($token_data['c_token'])) {
            $token = 'has token: '.substr($token_data['c_token'], 0, 10).'...';
        }
        
        return $this->render($response, 'Social/Views/fb-profile.twig', [
            'domains' => $domains,
            'token' => $token
        ]);
    }
    
    
    /**
     * funkce zajistujici vyzadani tokenu
     * je to v podstate manualni provedeni oauth autentikace. automaticke jde jen pomoci javascriptu.
     * 
     * musi probihat na dve faze, protoze vyzaduje interakci od uzivatele (v pripade ze neni prihlasen na fb a nema v browseru cookie na to)
     * 
     * pripravime si adresu s parametry, na kterou se nejprve presmerujeme
     * https://www.facebook.com/v12.0/dialog/oauth
     * 
     * pokud je clovek neprihlaseny, tak se v teto chvili na fb prihlasuje a schvaluje prava aplikaci (druhy vyzadovanych prav jsou nastavena v aplikaci)
     * pokud je prihlaseny, tak na fb to jen problikne a hned to jde zpet na glued
     * jeden z parametru je glued return uri
     * https://japex01.vaizard.xyz/social/fb/return/code
     * 
     * je mozne nechat si vratit primo token pomoci parametru response_type=token
     * ale fb ho vraci ve fragment casti adresy (za #), takze php se k nemu nedostane (fragment se neposila z browseru na server)
     * nechavam tam tedy puvodni tvar, kdy to vraci tzv "code"
     * 
     */
    
    public function fb_token_get($request, $response) {
        
        $user_id = $GLOBALS['_GLUED']['authn']['user_id'] ?? false;
        
        /*
https://www.facebook.com/v12.0/dialog/oauth?
  client_id={app-id}
  &redirect_uri={redirect-uri}
  &state={state-param}
  state je nejaky nas kontrolni retezec, davam tam jen tak pro zajimavost id uzivatele, ale muze tam byt cokoliv
  
  volitelne
  &response_type=token
  ten to ale da do fragment casti adresy (za #) a timpadem se k tomu server nedostane
        */
        
        $return_glued_address = 'https://japex01.vaizard.xyz/social/fb/return/code';
        $nase_state = $user_id;
        $login_fb_address = 'https://www.facebook.com/v12.0/dialog/oauth?client_id=943888169109322&redirect_uri='.urlencode($return_glued_address).'&state='.$nase_state;
        
        return $response->withRedirect($login_fb_address);
    }
    
    
    /**
     * funkce na adrese, kam se vraci fb po loginu
     * musime zachytit i nejake chyby a neschvaleni
     
     * token zatim nemame. mame jen tzv code, ktery musime poslat dale
     *
     * priklad, jak vypada cela navratova adresa, ktera prichazi z fb i s parametry
     * https://japex01.vaizard.xyz/social/fb/return?code=AQAEjS23Mz8ioEPb30mCdqgyiAnlSgwbwByWR_-2ExS1pn80ZdDMQXjxN4wmBneWuKXy7N1_H7YvH3yPa_bMlBWZWIu_5qWgv3Z1TpAgrVEaaVHh0OEVL02FNqvwh1QCZPdNngbVDg-13SiPgtp7pZ_jfOp0w5vf5bNx2UagtE_GLwFZ7nj8UQfMCBuJckO7uYdbwKdJHC4Xc9GVu6wdIbeMn8EZ3r9BOaFXgzb-2lpVVi5vpsHqhVHoWZoVT_oF1cBa6s-ZJUUfCyqnX1V7dE13hc2MK-e96HUsczb2pCkn2nbGxp2VgWzjepgQc3MhuApN61pRSdR1UoRynKvftDd3QI-iHU0XqwGOUhn3gMe9k0mLbAoUjZCVr-mFDywWuhE&state=4#_=_
     * jak je videt, prichazi ten dlouhy code a taky nam to vraci nase state
     */
    
    public function fb_return_code($request, $response) {
        
        $user_id = $GLOBALS['_GLUED']['authn']['user_id'] ?? false;
        $domains = $this->db->get('t_core_domains');
        
        // rozhodime si query do pole parametru
        $get_parametry = $request->getQueryParams();
        
        // code mame v $get_parametry['code']
        
        // zde muze nastat chyba a v adrese muze byt neco takoveho
        // to musime podchytit a ukazat chybovou stranku
        /*
pry by tam melo byt toto
YOUR_REDIRECT_URI?
 error_reason=user_denied
 &error=access_denied
 &error_description=Permissions+error.
 
ale realne tam je error_code a error_message, takze zase zrada
        */
        
        if (!empty($get_parametry['error_code'])) {
            
            $vystup = 'chyba: '.$get_parametry['error_message'];
            
            // ukazeme nasi vystupni stranku s chybovou hlaskou
            return $this->render($response, 'Social/Views/fb-profile.twig', [
                'domains' => $domains,
                'argdata' => $vystup
            ]);
        }
        else if (!empty($get_parametry['code'])) {
            // pokud je vse v poradku, musime vymenit code za access token
            /*
    GET https://graph.facebook.com/v12.0/oauth/access_token?
       client_id={app-id}
       &redirect_uri={redirect-uri}
       &client_secret={app-secret}
       &code={code-parameter}
            */
            
            /*
                posleme to uz pres GRAPH API
                return uri MUSI byt stejna, jako ta na kterou prislo code, protoze code je z ni tvoreno
                pri pouziti GRAPH API se uz ale nepresmerovava zpet, takze tam tu return uri jen zadame, ale na fb bude vyuzita jen pro overeni code
            */
            
            // inicializace graph api na nasi aplikaci
            $fb = new \Facebook\Facebook([
              'app_id' => '943888169109322',
              'app_secret' => '22b1a0cb2437d7ae6a570dc1be750fba',
              'default_graph_version' => 'v12.0'
            ]);
            
            // zde stejna return uri jako ve funkci fb_token_get
            $return_glued_address = 'https://japex01.vaizard.xyz/social/fb/return/code';
            
            // v GRAPH api musi byt pri volani get nejaky token.
            // jediny ktery mame je client token, ktery souvisi s aplikaci a je verejny (nevyprsi nikdy, ale da se zmenit v advanced nastaveni aplikace)
            // bylo by mozne pouzit jeste App Access Token, ale ten bychom museli vygenerovat v graph api explorer a ma docasnou trvanlivost
            // client token pro tuto aplikaci je 03a1c0c0826ea0fcc17f52ea7fc96b23
            // posleme tam to code, ktere jsme ziskali z prvniho navratu z fb
            
            try {
              $fbresponse = $fb->get(
                'oauth/access_token?client_id=943888169109322&redirect_uri='.urlencode($return_glued_address).'&client_secret=22b1a0cb2437d7ae6a570dc1be750fba&code='.$get_parametry['code'],
                '03a1c0c0826ea0fcc17f52ea7fc96b23'
              );
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                $vystup = 'graph failed: '.$e->getMessage();
                
                // ukazeme nasi vystupni stranku s chybovou hlaskou
                return $this->render($response, 'Social/Views/fb-profile.twig', [
                    'domains' => $domains,
                    'argdata' => $vystup
                ]);
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
                $vystup = 'facebook sdk failed: '.$e->getMessage();
                
                // ukazeme nasi vystupni stranku s chybovou hlaskou
                return $this->render($response, 'Social/Views/fb-profile.twig', [
                    'domains' => $domains,
                    'argdata' => $vystup
                ]);
            }
            
            // navratova data jsou v jednoduchem jsonu, takze pouzijeme getGraphNode a prevedem to na pole
            $token_data = $fbresponse->getGraphNode()->asArray();
            
            // token mame v $token_data['access_token'] a jedna se o User Access Token, pravdepodobne dlouhodoby, ale je treba vice testu proc to tak je
            // kratkodoby trva hodinu, dlouhodoby 3 mesice
            
            // ulozime si token k userovi $user_id
            $this->db->where("c_user_id", $user_id);
            $this->db->where("c_service", 'fb');
            $this->db->where("c_type", 'user');
            $tokens = $this->db->get('t_social_tokens');
            if (count($tokens) == 1) {
                $row = [ 'c_token' => $token_data['access_token'] ];
                $this->db->where('c_uid', $user_id);
                $this->db->update('t_social_tokens', $row);
            }
            else {
                $data = array (
                'c_user_id' => $user_id,
                'c_service' => 'fb',
                'c_type' => 'user',
                'c_data' => '',
                'c_token' => $token_data['access_token']
                );
                $this->db->insert('t_social_tokens', $data);
            }
            
            // TODO tady by asi bylo lepsi poslat to pres redirect na social.fb.profile, kde se to ukaze uz s vypsanym tokenem z db
            
            // zatim tu stranku ukazeme bez redirectu, se stazenym tokenem v info casti dole
            return $this->render($response, 'Social/Views/fb-profile.twig', [
                'domains' => $domains,
                'argdata' => 'stazeny token: '.$token_data['access_token']
            ]);
        }
        else {
            // tady je slepa vetev, kdyby nekdo tu stranku zadal rucne, tak aby se to ukazalo s chybou
            
            return $this->render($response, 'Social/Views/fb-profile.twig', [
                'domains' => $domains,
                'argdata' => 'chyba, sem nemate pristup'
            ]);
        }
        
    }
    
    
    /**
     * pokus o stazeni nejakych udaju
     * pouzijeme drive stazeny user access token z db
     * a zkusime si pres graph api vyzadat nejake udaje
     * friend list to ale nebude, protoze zadat toto pravo do aplikace je pomerne narocne
     * to si pak pavel udela sam u sve aplikace
     */
    
    public function fb_profile_get_me($request, $response) {
        
        $user_id = $GLOBALS['_GLUED']['authn']['user_id'] ?? false;
        $domains = $this->db->get('t_core_domains');
        
        // nacteme fb user token
        $this->db->where("c_user_id", $user_id);
        $this->db->where("c_service", 'fb');
        $this->db->where("c_type", 'user');
        $token_data = $this->db->getOne('t_social_tokens');
        if (!empty($token_data['c_token'])) {
            
            // inicializace graph api na nasi aplikaci
            $fb = new \Facebook\Facebook([
              'app_id' => '943888169109322',
              'app_secret' => '22b1a0cb2437d7ae6a570dc1be750fba',
              'default_graph_version' => 'v12.0'
            ]);
            
            // stahneme nejaka data o uzivateli
            try {
              $fbresponse = $fb->get(
                'me',
                $token_data['c_token']
              );
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                $vystup = 'graph failed: '.$e->getMessage();
                
                // ukazeme nasi vystupni stranku s chybovou hlaskou
                return $this->render($response, 'Social/Views/fb-profile.twig', [
                    'domains' => $domains,
                    'argdata' => $vystup
                ]);
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
                $vystup = 'facebook sdk failed: '.$e->getMessage();
                
                // ukazeme nasi vystupni stranku s chybovou hlaskou
                return $this->render($response, 'Social/Views/fb-profile.twig', [
                    'domains' => $domains,
                    'argdata' => $vystup
                ]);
            }
            
            // navratova data jsou v jednoduchem jsonu, takze pouzijeme getGraphNode
            //$json_data = $fbresponse->getGraphNode();
            // nebo takto to bude lepsi
            $json_data = $fbresponse->getDecodedBody();
            
            $vystup = 'fb vratil: '.print_r($json_data, true);
        }
        else {
            $vystup = 'nemas ulozeny user token';
        }
        
        return $this->render($response, 'Social/Views/fb-profile.twig', [
            'domains' => $domains,
            'argdata' => $vystup
        ]);
    }
    
}

