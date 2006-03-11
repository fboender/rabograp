<?php
/**
 * (C) Michiel van Baak, 2006
 * with small modifications by Ferry Boender
 * 
 * Webinterface to load mut.txt into rabograp Robograp is (C) Ferry Boender
 *
 * This is just a lame php to make it more easy for my wife to use ;)
 */

 switch ($_REQUEST["action"]) {
    case "process" :
        process_file();
        break;
    default :
        show_upload_screen();
        break;
    /* end of switch */
 }
 
 function process_file() {
    if (is_array($_FILES["userfile"]) && file_exists($_FILES["userfile"]["tmp_name"])) {
        $scriptfolder = dirname($_SERVER["SCRIPT_FILENAME"]);
        if (@move_uploaded_file($_FILES["userfile"]["tmp_name"], $scriptfolder."/mut.txt")) {
            $processcommand = "./rabograp.php";
            $output = system($processcommand, $returnval);
            if ($returnval) {
                show_upload_screen(2);
            } else {
                header("Location: rabograp.html");
            }
        } else {
            show_upload_screen(3);
        }
    } else {
        show_upload_screen(1);
    }
 }

 function show_upload_screen($error = 0) {
    ?>
    <html>
    <head>
        <title>RaboGRAP</title>
        <link rel="stylesheet" href="rabograp.css" type="text/css" />
    </head>
    <body>
        <div class="header">RABO Gegenereerde Rapportages</div>
        <div class="body">
            <h1>Verwerk transactie-bestand</h1>
            <?php
            if ($error != 0) {
                switch ($error) {
                    case 1 :
                        ?>
                        <div class="error"><p><h1>Fout</h1>Er is een fout opgetreden met het uploaden van uw bestand. Probeer het nogmaals.</p></div>
                        <?php
                        break;
                    case 2 :
                        ?>
                        <div class="error"><p><h1>Fout</h1>Er is een fout opgetreden met het verwerken van uw bestand. Download het bestand opnieuw en probeer het nogmaals.</p></div>
                        <?php
                        break;
                    case 3 :
                        ?>
                        <div class="error"><p><h1>Fout</h1>De webserver is niet geconfigureerd voor het verwerken van geuploade bestanden. Neem contact op met uw beheerder.</p></div>
                        <?php
                        break;
                    default:
                        ?>
                        <div class="error"><p><h1>Fout</h1>Er is een onbekende fout opgetreden.</p></div>
                        <?php
                }
                ?>
                <a href="index.php">Probeer het nogmaals</a>
                <?php
            } else {
                ?>
                <p>U kunt hier uw gedownloadde Rabobank transacties (mut.txt) bestand inlezen. Als het bestand correct is zal het door rabograp omgezet worden in handige overzichten.</p>
                <p>Gebruik:</p>
                <ul>
                    <li>Klik op 'Browse' (of 'Bladeren', afhankelijk van de taal van uw besturingssysteem)</li>
                    <li>Lokaliseer het 'mut.txt' bestand wat u van de Rabobank Internetbankieren site heeft gedownload.</li>
                    <li>Dubbelklik op het bestand</li>
                    <li>Klik op 'Verwerken'.</li>
                </ul>
                <form id="mutupload" name="mutupload" method="post" action="index.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process" />
                <input type="file" name="userfile" size="50" />
                <input type="submit" value="Verwerken" />
                </form>
                <?php
            }
            ?>
        </div>
        <div class="footer"><a href='http://www.electricmonk.nl'>RaboGRAP</a> is &copy; Ferry Boender - 2006. Released under the <a href='http://www.gnu.org/copyleft/gpl.html'>GPL</a> license.</div>
    </body>
    </html>
    <?
 }
?>
