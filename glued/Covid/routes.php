<?php
use Glued\Contacts\Controllers\ContactsController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Respect\Validation\Validator as v;


$app->get ('/covid/zakladace/import', function(Request $request, Response $response) { 
    $splitter = new \VIISON\AddressSplitter\AddressSplitter;
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
        $gdpr = $row[6]; // gdpr yes
        $ad = $row[7]; // adresa
        $ye = $row[13]; // dodáno
        $xx = $row[8]; // problem

        if ($i == 0) { $ye = "Dodáno"; $xx = "Špatná data"; }

        echo "<tr>";
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
        unset($v);
        $i++;
    }
    echo '</table>';
    return $response;
}) -> setName('covid.zakladace.import');
