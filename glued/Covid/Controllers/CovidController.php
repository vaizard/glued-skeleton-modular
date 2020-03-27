<?php
declare(strict_types=1);

namespace Glued\Covid\Controllers;
use Glued\Core\Classes\Auth\Auth;
use Glued\Core\Classes\Crypto\Crypto;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use \Exception;

class CovidController extends AbstractTwigController

{

public function zakladace_import_v1($request, $response)
    {

    $inputFileName = '/var/www/html/glued-skeleton/private/data/export.xlsx';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = [];
    foreach ($worksheet->getRowIterator() AS $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); // This loops through all cells,
        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }
        $bold = $worksheet->getStyle('A'.$row->getRowIndex())->getFont()->getBold();
        $cells[] = $bold;

        $rows[] = $cells;
    }
    //print("<pre>".print_r($rows,true)."</pre>");
    echo '<html><head>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/milligram/1.3.0/milligram.css">
    </head></html>';
    echo '<table>';

    $i = 0;
    foreach ($rows as $row) {

        $ts = $row[0];
        $jm = $row[1];
        $ph = str_replace(' ', '', $row[2]);
        if ( (is_numeric($ph)) and (strlen($ph)==9) ) { 
            $ph = "+420".$ph; 
        }
        $v['ph'] = v::phone()->length(13, 13)->validate($ph);
        $s['ph'] = "color: red;";
        if ($v['ph'] == 1) { $s['ph'] = "color: green;"; }

        
        $em = str_replace(' ', '', $row[3]);
        $pocet = $row[4];
        $v['em'] = v::email()->validate($em);
        $s['em'] = "color: red;";
        if ($v['em'] == 1) { $s['em'] = "color: green;"; }
        $no = $row[5]; // pozn
        $gdpr = 0;
        if ($row[6]=="ANO") {$gdpr = 1;} // gdpr yes
        $ad = $row[7]; // adresa
        $ye = (int)$row[13]; // dodáno
        $xx = $row[8]; // problem

        if ($i == 0) { $ye = "Dodáno"; $xx = "Špatná data"; }

        echo "<tr>";
        echo "<td>".$i."</td>";
        echo "<td>".$ts."</td>";
        echo "<td>".$jm."</td>";
        echo "<td>".$pocet."</td>";
        echo "<td style='".$s['ph']."'>".$ph."</td>";
        echo "<td style='".$s['em']."'>".$em."</td>";
        echo "<td>".$no."</td>";
        echo "<td>".$gdpr."</td>";
        echo "<td>".$ad."</td>";
        echo "<td>".$ye."</td>";
        echo "<td>".$xx."</td>";
        echo "</tr>";

        if ($v['em'] == 0) { $em = ""; }
        if ($v['ph'] == 0) { $ph = ""; }
        if ($i != 0) {
            $data = Array ("c_uid" => $i,
                           "c_ts" => $ts,
                           "c_name" => $jm,
                           "c_phone" => $ph,
                           "c_email" => $em,
                           "c_notes" => $no,
                           "c_gdpr_yes" => $gdpr,
                           "c_address" => $ad,
                           "c_amount" => $pocet,
                           "c_handovered" => (int)$ye,
                           "c_delivered" => 0,
                           "c_bad_data" => $xx,
                           "c_row_hash" => md5($i.$ts),
            );
            $updateColumns = Array ("c_handovered", "c_phone", "c_email");
            $lastInsertId = "c_uid";
            $this->db->onDuplicate($updateColumns, $lastInsertId);
            $c_uid = $this->db->insert ('t_covid_zakladace', $data);
            echo "<td>". $this->db->getLastError()."</td>";
        }
        echo "</tr>";
        unset($v);
        $i++;

    }
    echo '</table>';
    return $response;
    }

public function zakladace_stav($request, $response, array $args = [])
    {

    $email = "";
    if (isset($args['email'])) { $email = $args['email']; }
    if (isset($request->getparams()['email'])) { $email = $request->getparams()['email']; }

    if ($email != "") {    
        $this->db->where("c_email", $email);
        $result = $this->db->getOne("t_covid_zakladace");
    }

    echo '<!DOCTYPE html>
    <html><head>
    
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/milligram/1.3.0/milligram.css">
    
    </head>
    <body>
    <div class="container">
    <br>
    <h1>Kde je můj zakladač? 💪</h1>
    <form action="" method="get">
    <fieldset>
    <label for="email">Prosím zadejte Váš e-mail</label> 
    <input name="email" id="email" value="'.$email.'" type="email" required><input type="submit" value="Najdi zakladač!">
    </fieldset>
    </form>
    <br>
    ';

    if ($email != "") {
        if (!isset($result['c_email'])) { echo "Ajaj, je Váš mail správně zadán? Nemůžeme Vás v systému najít. Pokud jste si zakladač zdarma ještě neobjenal(a) a pomůže Vám, <a href='https://pomoc.industra.space/#zakladace'>klikněte zde</a>.<br><br>"; }
        else {
            echo "Našli jsme Vás <span style='color: red;'>❤️</span> ";
            if($result['c_handovered'] == 1) { echo "Váš zakladač (".$result['c_amount']." ks) někdo (doufáme, že Vy) už vyzvedl osobně. Pokud k vám nedoputoval, nebo potřebujete další, dejte nám prosím vědět.<br><br>"; } 
            if($result['c_delivered'] == 1) { echo "Váš zakladač (".$result['c_amount']." ks) jede poštou za Vámi na adresu ".$result['c_address']." 🚐<br><br>"; } 
            if(($result['c_handovered'] == 0) and ($result['c_delivered'] == 0)) { echo 'Váš zakladač ('.$result["c_amount"].' ks) na Vás čeká 🐶 Prosíme vyzvedněte si jej kdykoliv mezi <b>10 a 19 hod. v Industře, Masná 9, Brno</b>, nebo nám prosím upřesněte Vaši adresu, pošleme Vám ho.<br><br>

                <form action="/covid/zakladace/adresa" method="post">
                <fieldset>
                <input type="hidden" name="hash" value="'.$result['c_row_hash'].'">
                <div class="row">
                <div class="column">
                <label for="ulice">Ulice a číslo</label> 
                <input type="text" name="ulice" id="ulice">
                </div>
                <div class="column">
                <label for="obec">Obec</label> 
                <input type="text" name="obec" id="obec">
                </div>
                </div>
                <div class="row">
                <div class="column">
                <label for="psc">PSČ</label> 
                <input type="text" name="psc" id="psc">
                </div>
                <div class="column">
                <label for="upresneni">Upřesnění adresy (nevyplňujte, pokud není třeba)</label> 
                <input type="text" name="upresneni" id="upresneni">
                </div>
                </div>
                <input type="submit" value="Ulož adresu!">
                </fieldset>
                </form>

            '; }
        }


    }
    echo '
    <h3>Děkujeme, že táké pomáháte. Jste skvělí!</h3>

     <b>Zakladač pro Vás s láskou tiskne, balí a zasílá náš dobrovolnický tým.</b> Chcete nám pomoct vyrábět nejen zakladače, ale také roušky (do nemocnic) nebo celoobličejové štíty pro lékaře? Přispějte prosím na náš <b>transparentní účet <a href="https://ib.fio.cz/ib/transparent?a=2500781658">2500781658 / 2010</a> - pomůže doslova každá koruna</b>. Váš dar nám pomůže zajistit materiál, výrobní prostředky a zázemí pro distribuci pomoci. <b>Potřebujete roušky, štíty, další zakladače, nebo jiné pomůcky?</b> <a href="https://pomoc.industra.space">Obraťte se na nás, rádi pomůžeme</a>.

    </div></body></html>';
    return $response;
    }

public function zakladace_adr($request, $response, array $args = [])
    {

    $b = $request->getParsedBody();
    print_r($b);

    $data = Array (
        'c_addr_street' => $b['ulice'],
        'c_addr_city' => $b['obec'],
        'c_addr_zip' => $b['psc'],
        'c_addr_note' => $b['upresneni'],
    );
    $this->db->where ('c_row_hash', $b['hash']);

    echo '<!DOCTYPE html>
    <html><head>
    
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/milligram/1.3.0/milligram.css">
    
    </head>
    <body>
    <div class="container">
    <br>';
    if ($this->db->update ('t_covid_zakladace', $data)) {
        echo '
    <h1>Máme to!</h1>
    <b>Zakladač Vám doručí Česká pošta! Díky moc, jste skvělí. <a href="https://pomoc.industra.space">Chcete vědět víc?</a> <a href="https://pomoc.industra.space#pridej-se">Chcete vědět víc?</a>Chcete přispět?</a></b>';
    } else {
        echo '<h1>Aj aj, něco se nepovedlo</h1> 
        <b>Kontaktujte nás prosím na +420 776 706 254, děkujeme.</b><br>' . $this->db->getLastError();
    }

    echo '</div></body></html>';
    return $response;
    }


}