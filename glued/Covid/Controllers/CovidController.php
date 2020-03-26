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

}