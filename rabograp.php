#!/usr/bin/env php
<?

/* 
 * RaboGRAP
 *
 * Genereert statistieken, overzichten en totalen van een rekeningoverzicht 
 * ------------------------------------------------------------------------
 * 
 * Hoe te gebruiken:
 *
 *   - Ga naar RaboBank Internet bankieren.
 *   - Log in met je Raboreader
 *   - Ga naar 'Betalen en Sparen'.
 *   - Ga naar 'Download'.
 *   - Kies 'Alle transacties van de volgende rekening [      ][V]'
 *   - Maak een keuze uit een rekening
 *   - Voer als begindatum '01-01-2004' in. (eerder kan niet)
 *   - Voer als einddatum vandaag in.
 *   - Kies het 'Kommagescheiden formaat'.
 *   - Klik download.
 *   - Run dit script: php ./rabograp.php
 *   
 * Het script genereerd nu je onzin statistiekjes.
 */

error_reporting(E_ALL);

define("ANR", "accountNr");
define("CUR", "currency");
define("DAT", "date");
define("DCR", "debitCredit");
define("AMM", "ammount");
define("CAN", "counterAccountNr");
define("CPR", "counterParty");
define("COD", "code");
define("DES", "description");

define ("IS", 1);
define ("IS_NOT", 2);
define ("MORE_THEN", 3);
define ("LESS_THEN", 4);
define ("CONTAINS", 5);
define ("CONTAINS_NOT", 6);

$codes = array(
	"AC" => "Acceptgiro",
	"BA" => "Betaalautomaat",
	"BY" => "Bijschrijving",
	"CK" => "Chipknip",
	"DA" => "Diverse afboekingen",
	"GA" => "Geldautomaat",
	"KO" => "Kasopname",
	"MA" => "Machtiging",
	"OV" => "Overschrijving",
	"PB" => "Periodieke betaling",
	"TB" => "Telebankieren",
	"TG" => "Telegiro",
);

class RTransactionControl {

	public $transactions = array();

	public function __construct($file = null) {
		if ($file != null) {
			$this->loadTransactions($file);
		}
	}

	public function loadTransactions($file) {
		if (!$fileContents = file_get_contents($file)) {
			print("Couldn't open and read '$file'. Abort.");
			exit();
		}

		foreach(explode("\n", ($fileContents)) as $line) {
			try {
				$transaction = new RTransaction($line);
				$this->addTransaction($transaction);
			} catch (Exception $e) {
				//print($e);
			}

		}
	}

	public function addTransaction($transaction) {
		if (!get_class($transaction) == "RTransaction") {
			throw new Exception("Invalid transaction type added.");
		}
		$this->transactions[] = $transaction;
	}

	public function dumpText() {
		foreach($this->transactions as $t) {
			printf("%10s %s %10s %19s '%s' '%s'\n",
				$t->getFmtField(DAT),
				$t->getFmtField(DCR),
				$t->getFmtField(AMM),
				$t->getFmtField(COD),
				$t->getFmtField(CPR),
				$t->getFmtField(DES)
			);
		}
	}

	public function dumpHTMLRow() {
		$out = "";
		$prevTransaction = false;
		foreach($this->transactions as $t) {
			if ($t->{DCR} == 'D') { $trClass= "debit"; } else { $trClass = "credit"; }

			$buf = sprintf("<tr class='$trClass'><td>%s</td><td>%s</td><td align='right'>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				$t->getFmtField(DAT),
				$t->getFmtField(DCR),
				$t->getFmtField(AMM),
				$t->getFmtField(COD),
				$t->getFmtField(CPR),
				$t->getFmtField(DES)
			);

			if ($prevTransaction && strftime("%m", $t->{DAT}) != strftime("%m", $prevTransaction->{DAT})) {
				$buf = str_replace("<td", "<td class='newMonth'", $buf);
			}

			$out .= $buf;

			$prevTransaction = $t;
		}

		return($out);
	}

	public function sortTransactions($field, $direction = SORT_ASC) {
		if ($direction == SORT_ASC) {
			$cmp = create_function('$a,$b', 'if ($a->'.$field.' == $b->'.$field.') { return (0); }; return($a->'.$field.' > $b->'.$field.' ? -1 : 1);');
		} else {
			$cmp = create_function('$a,$b', 'if ($a->'.$field.' == $b->'.$field.') { return (0); }; return($a->'.$field.' > $b->'.$field.' ? 1 : -1);');
		}
		usort($this->transactions, $cmp);
	}

	public function getFromTo($from, $to) {

		$transactions = new RTransactionControl();

		$this->sortTransactions(DAT, SORT_DESC);
		foreach($this->transactions as $transaction) {
			if ($transaction->date >= $to) {
				break;
			}
			if ($transaction->date >= $from) {
				$transactions->addTransaction(&$transaction);
			}
		}

		return($transactions);
	}

	public function getFiltered($field, $operator, $value) {

		$transactions = new RTransactionControl();

		foreach($this->transactions as $transaction) {
			$filtered = false;
			switch($operator) {
				case IS    : if ($transaction->{$field} != $value) { $filtered = true; } break;
				case IS_NOT: if ($transaction->{$field} == $value) { $filtered = true; } break;
				case MORE_THEN: if ($transaction->{$field} < $value) { $filtered = true; } break;
				case LESS_THEN: if ($transaction->{$field} > $value) { $filtered = true; } break;
				case CONTAINS: if (strpos($value, $transaction->{$field}) === 0) { $filtered = true; } break;
				case CONTAINS_NOT: if (strpos($value, $transaction->{$field}) === 0) { $filtered = true; } break;
			}

			if (!$filtered) {
				$transactions->addTransaction(&$transaction);
			}
		}

		return($transactions);
		
	}

	public function getSum($field) {
		$sum = 0;

		foreach($this->transactions as $transaction) {
			if (is_numeric($transaction->{$field})) {
				$sum += $transaction->{$field};
			}
		}

		return($sum);
	}

	public function getUnique($field) {
		$uniqueValues = array();

		foreach($this->transactions as $transaction) {
			if (!in_array($transaction->{$field}, $uniqueValues)) {
				$uniqueValues[] = $transaction->{$field};
			}
		}

		return($uniqueValues);
	}

	public function getSmallest($field) {
		$this->sortTransactions($field, SORT_DESC);
		if (count($this->transactions) > 0) {
			return($this->transactions[0]->{$field});
		}
	}

	public function getLargest($field) {
		$this->sortTransactions($field, SORT_ASC);
		if (count($this->transactions) > 0) {
			return($this->transactions[0]->{$field});
		}
	}
}

class RTransaction {
	public function __construct($line) {
		$fields = explode(',', $line);
		if (count($fields) != 16) {
			throw new Exception("unrecognised line format");
		}
		for ($i = 0; $i < count($fields); $i++) {
			$fields[$i] = ltrim(rtrim($fields[$i], '"'), '"');
		}

		$this->accountNr = $fields[0];
		$this->currency = $fields[1];
		$this->date = mktime(0,0,0,(int)substr($fields[2],4,2), (int)substr($fields[2],6,2), (int)substr($fields[2],0,4));
		$this->debitCredit = $fields[3];
		$this->ammount = (float)$fields[4];
		$this->counterAccountNr = $fields[5];
		$this->counterParty = $fields[6];
		/* Field 7 unknown */
		$this->code = $fields[8];
		/* Field 9 unknwon */
		$this->description = array();
		$this->description[] = $fields[10];
		$this->description[] = $fields[11];
		$this->description[] = $fields[12];
		/* Field 13 unknwon */
		/* Field 14 unknown */
	}

	public function getFmtField($field) {
		global $codes;
		$ret = false;

		switch($field) {
			case ANR: $ret = $this->accountNr; break;
			case CUR: if ($this->currency == "EUR") { $ret = "&euro;"; } else { $ret = "Fl. "; } break;
			case DAT: $ret = strftime("%a %d %b %Y", $this->date); break;
			case DCR: if ($this->debitCredit == 'D') { $ret = '-'; } else { $ret = '+'; } break;
			case AMM: $ret = number_format($this->ammount, 2); break;
			case CAN: $ret = $this->counterAccountNr; break;
			case CPR: $ret = $this->counterParty; break;
			case COD: $ret = $codes[$this->code]; break;
			case DES: $ret = implode(", ", $this->description); break;
		}

		return($ret);
	}
}

class RReport {
	public function __construct($title) {
		$this->htmlHeader = "
			<html>
			<head>
				<title>TITLE</title>
				<link rel='stylesheet' href='rabograp.css' type='text/css' />
			</head>
			<body>
				<div class='header'>$title</div>
                <div class='menu'><a href='rabograp.html'>Hoofdpagina</a></div>
				<div class='body'>
			";
		$this->htmlFooter = "
				</div>
				<div class='footer'>Generated by <a href='http://www.electricmonk.nl'>RaboGRAP</a>. RaboGRAP is &copy; Ferry Boender - 2006. Released under the <a href='http://www.gnu.org/copyleft/gpl.html'>GPL</a> license.</div>
			</body>
			";
	}

	public function addOutput($output) {
		$this->out .= $output;
	}
	
	public function save($filename) {
		file_put_contents($filename, $this->htmlHeader.$this->genIndex($this->out).$this->out.$this->htmlFooter);
	}

    public function genIndex($fc) {

        preg_match_all("|<a name='(.*)'><h([0-9])>(.*)</h([0-9])></a>|", $fc, $headings);

        $prevHeadingNr = 0;
        $out = "";
        $out .= '
            <div class="contents">
            <h1>Inhoud</h1>
            ';
            for ($i = 0; $i < count($headings[2]); $i++) {
                if ($headings[2][$i] > $prevHeadingNr) {
                    $out .= "<ul>\n";
                }
                if ($headings[2][$i] < $prevHeadingNr) {
                    $out .= "</ul>\n";
                }
                $out .= "<li><a href='#".$headings[1][$i]."'>".$headings[3][$i]."</a></li>\n";

                $prevHeadingNr = $headings[2][$i];
            }
            $out .= '
            </ul>
            </div>
        ';

        return($out);

    }

	public function genOverviewNrOfTrans($t) {
		$tDebit = $t->getFiltered(DCR, IS, 'D');
		$tCredit = $t->getFiltered(DCR, IS, 'C');

		$out = "";
		$out .= "<a name='transactions'><h2>Transacties</h2></a>\n";
		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "<tr><th>Totaal aantal afschrijvingen:</th><td class='debit' align='right'>".count($tDebit->transactions)."</td></tr>";
		$out .= "<tr><th>Totaal aantal bijschrijvingen:</th><td class='credit' align='right'>".count($tCredit->transactions)."</td></tr>";
		$out .= "<tr><th>Totaal aantal transacties:</th><td align='right' style='border-top: 1px solid #000000'>".count($t->transactions)."</td></tr>";
		$out .= "</table>\n";
		return($out);
	}

	public function genOverviewDebCred($t) {
		global $codes;

		$tDebit = $t->getFiltered(DCR, IS, 'D');
		$tCredit = $t->getFiltered(DCR, IS, 'C');

		$out = "";

		$out .= "<a name='debitcredit'><h2>Af/Bijschrijvingen</h2></a>\n";
		$out .= "<a name='debitcredittotal'><h3>Totaal</h3></a>\n";
		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "  <tr class='debit'><th>Totaal afgeschreven:</th><td class='debit' align='right'>&euro;".number_format($tDebit->getSum(AMM), 2)."</td></tr>";
		$out .= "  <tr class='credit'><th>Totaal bijgeschreven:</th><td class='credit' align='right'>&euro;".number_format($tCredit->getSum(AMM), 2)."</td></tr>";
		$out .= "  <tr><th>Totaal:</th><td align='right' style='border-top: 1px solid #000000'>&euro;".number_format($t->getSum(AMM), 2)."</td></tr>";
		$out .= "</table>\n";
		$out .= "<a name='pertype'><h3>Per type</h3></a>\n";
		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "  <tr><th>&nbsp;</th><th class='debit'>Af</th> <th class='credit'>Bij</th> <th>Totaal</th> </tr>";
		foreach($codes as $code => $descr) {
			$tCode = $t->getFiltered(COD, IS, $code);
			$tDebitCode = $tCode->getFiltered(DCR, IS, 'D');
			$tCreditCode = $tCode->getFiltered(DCR, IS, 'C');
			$out .= "
				<tr>
					<th>".$descr.":</th>
					<td class='debit' align='right'>&euro;".number_format($tDebitCode->getSum(AMM), 2)."</td>
					<td class='credit' align='right'>&euro;".number_format($tCreditCode->getSum(AMM), 2)."</td>
					<td align='right'>&euro;".number_format($tCode->getSum(AMM), 2)."</td>
				</tr>";
		}
		$out .= "</table>\n";

		return($out);
	}

	public function genTransPerCounterParty($t) {
		$out = "";
		$cnt = 0;
		$out .= "<a name='all_trans_cpr'><h1>Alle transacties (Tegenpartij)</h1></a>\n";

		$uniqCPRs = $t->getUnique(CPR);
		sort($uniqCPRs);
		foreach($uniqCPRs as $uniqCPR) {
			$tCPR = $t->getFiltered(CPR, IS, $uniqCPR);
			$tCPR->sortTransactions(DAT, SORT_DESC);

			$out .= "<a name='all_trans_cpr_".str_replace(' ', '_', strtolower($uniqCPR))."'><h2>".strtolower($uniqCPR)."</h2></a>\n";
			$out .= "<table cellpadding=0 cellspacing=0>\n";
			$out .= "<tr> <th>Datum</th> <th>+/-</th> <th>Bedrag</th> <th>Type</th> <th>Omschrijving</th> </tr>\n";

			foreach($tCPR->transactions as $st) {
				if ($st->{DCR} == 'D') { $strClass= "debit"; } else { $strClass = "credit"; }

				$out .= sprintf("<tr class='$strClass'><td>%s</td><td>%s</td><td align='right'>&euro;%s</td><td>%s</td><td>%s</td></tr>\n",
					$st->getFmtField(DAT),
					$st->getFmtField(DCR),
					$st->getFmtField(AMM),
					$st->getFmtField(COD),
					$st->getFmtField(DES)
				);

				//print(number_format(memory_get_usage())." ".number_format(strlen($out))."\n");
				$cnt++;
			}
			$out .= "<tr><td colspan='2'<td align='right'>&euro;".number_format($tCPR->getSum(AMM), 2)."</td><td colspan='3'>: $cnt transacties</td></tr>\n";
			$out .= "</table>\n";

			$cnt = 0;
		}

		return($out);
	}

	public function genTransAll($t) {
		global $codes;

		$t->sortTransactions(DAT, SORT_DESC);

		$cntD = 0;
		$cntC = 0;
		$sumD = 0;
		$sumC = 0;
		$out = "";
		$out .= "<a name='all_trans_date'><h1>Alle transacties (datum)</h1></a>\n";
		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "<tr> <th>Datum</th> <th>+/-</th> <th>Bedrag</th> <th>Type</th> <th>Tegenpartij</th> <th>Omschrijving</th> </tr>\n";

		$prevTransaction = false;
		foreach($t->transactions as $st) {
			if ($st->{DCR} == 'D') { $strClass= "debit"; } else { $strClass = "credit"; }

			if (!$prevTransaction || strftime("%m", $st->{DAT}) != strftime("%m", $prevTransaction->{DAT})) {
				if ($cntD != 0 || $cntC != 0) {
					$out .= "<tr><td>$cntD transacties</td><td>-</td><td class='debit' align='right'>&euro;".number_format($sumD, 2)."</td><td colspan='100'>&nbsp;</td></tr>";
					$out .= "<tr><td>$cntC transacties</td><td>+</td><td class='credit' align='right'>&euro;".number_format($sumC, 2)."</td><td colspan='100'>&nbsp;</td></tr>";
				}
				$out .= "<tr class='month'><td colspan='100'><a href='rabograp_".strftime("%Y",$st->{DAT})."_".strftime("%m", $st->{DAT}).".html'>".strftime("%B", $st->{DAT})."</a></td></tr>";
				$cntD = $cntC = 0;
				$sumD = $sumC = 0;
			}

			$out .= sprintf("<tr class='$strClass'><td>%s</td><td>%s</td><td align='right'>&euro;%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				$st->getFmtField(DAT),
				$st->getFmtField(DCR),
				$st->getFmtField(AMM),
				$st->getFmtField(COD),
				$st->getFmtField(CPR),
				$st->getFmtField(DES)
			);

			$prevTransaction = $st;
			if ($st->{DCR} == 'D') {
				$sumD += $st->{AMM};
				$cntD++;
			} else {
				$sumC += $st->{AMM};
				$cntC++;
			}
		}

		/* For last month */
		if ($cntD != 0 || $cntC != 0) {
			$out .= "<tr><td>$cntD transacties</td><td>-</td><td class='debit' align='right'>&euro;".number_format($sumD, 2)."</td><td colspan='100'>&nbsp;</td></tr>";
			$out .= "<tr><td>$cntC transacties</td><td>+</td><td class='credit' align='right'>&euro;".number_format($sumC, 2)."</td><td colspan='100'>&nbsp;</td></tr>";
		}

		$out .= "</table>\n";

		return($out);
	}

	public function genGraphAllMonths($t) {
		$t->sortTransactions(DAT, SORT_DESC);
		$year = (int)strftime("%Y", $t->getSmallest(DAT));

		/* Get sums for each month */
		$monthSumsD = $monthSumsC = array();
		$maxSumD = $maxSumC = 0;
		for ($i = 1; $i <= 12; $i++) {
			$tMonth = $t->getFromTo(mktime(0,0,0,$i,1, $year), mktime(0,0,0,$i+1,1,$year));
			$tMonthD = $tMonth->getFiltered(DCR, IS, 'D');
			$tMonthC = $tMonth->getFiltered(DCR, IS, 'C');
			$tSumD = $tMonthD->getSum(AMM);
			$tSumC = $tMonthC->getSum(AMM);

			$monthSumsD[$i] = $tSumD;
			$monthSumsC[$i] = $tSumC;

			if ($tSumD > $maxSumD) { $maxSumD = $tSumD; }
			if ($tSumC > $maxSumC) { $maxSumC = $tSumC; }
		}

		$out = "";
		$out .= "<a name='graph'><h1>Grafiek (maand)</h1></a>\n";
		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "<tr>";
		for ($i = 1; $i <= 12; $i++) {
			$out .= "<th class='graph' align='center'>$i</th>";
		}
		$out .= "</tr>";
		$out .= "<tr valign='bottom'>";
		for ($i = 1; $i <= 12; $i++) {
			$out .= "<td class='graph' align='center'><div class='debit' style='margin: 0px; padding: 0px; border: 1px solid #000000; display:block; width: 15px; height:".floor((($monthSumsD[$i] / $maxSumD) * 100))."px;'></div><font style='font-size:x-small'>".sprintf("%.1f",$monthSumsD[$i]/1000)."k</font></td>";
		}
		$out .= "</tr>";
		$out .= "</table>\n";

		$out .= "<table cellpadding=0 cellspacing=0>\n";
		$out .= "<tr>";
		for ($i = 1; $i <= 12; $i++) {
			$out .= "<th class='graph' align='center'>$i</th>";
		}
		$out .= "</tr>";
		$out .= "<tr valign='bottom'>";
		for ($i = 1; $i <= 12; $i++) {
			$out .= "<td class='graph' align='center'><div class='credit' style='margin: 0px; padding: 0px; border: 1px solid #000000; display:block; width: 15px; height:".floor((($monthSumsC[$i] / $maxSumC) * 100))."px;'></div><font style='font-size:x-small'>".sprintf("%.1f",$monthSumsC[$i]/1000)."k</font></td>";
		}
		$out .= "</tr>";
		$out .= "</table>\n";

		return($out);
		
	}

}
//print(number_format(memory_get_usage())."\n");
$tr = new RTransactionControl("mut.txt");
/* Main index page */
$pageMain = new RReport("RABO Gegenereerde Rapportages");
$pageMain->addOutput("<a name='about'><h1>Over RaboGRAP</h1></a>\n");
$pageMain->addOutput("<p>Deze rapportages zijn gegenereerd door middel van <a href='http://www.electricmonk.nl'>RaboGRAP</a>. RaboGRAP is &copy; Ferry Boender - 2006 en is verkrijgbaar onder de vrije software <a href='http://www.gnu.org/copyleft/gpl.html'>GPL</a> licensie.</p>");
$pageMain->addOutput("<p>De auteur van deze software accepteert GEEN ENKELE VERANTWOORDING voor de inhoud en correctheid van deze rapportages! RaboGRAP is vrij en gratis verkrijgbaar en biedt geen ENKELE GARANTIE OVER DE (CORRECTE) WERKING VAN DEZE SOFWARE!. Voor meer informatie, zie de <a href='http://www.gnu.org/copyleft/gpl.html'>GPL</a> licensie overeenkomst.</p>");
$pageMain->addOutput("<a name='total_overview'><h1>Totaal overzichten</h1></a>");
$pageMain->addOutput("<ul>");
$pageMain->addOutput("<li><a href='rabograp_all.html'>Alles</a></li>\n");
$pageMain->addOutput("</ul>");

$pageMain->addOutput("<a name='year_overview'><h1>Jaar overzichten</h1></a>");
$pageMain->addOutput("<ul>");
for($year = (int)strftime("%Y", $tr->getSmallest(DAT)); $year <= (int)strftime("%Y", $tr->getLargest(DAT)); $year++) {
	$pageMain->addOutput("<li><a href='rabograp_$year.html'>$year</a></li>\n");
}
$pageMain->addOutput("</ul>");

$pageMain->addOutput("<a name='month_overview'><h1>Maand overzichten</h1></a>");
$pageMain->addOutput("<ul>");
for($year = (int)strftime("%Y", $tr->getSmallest(DAT)); $year <= (int)strftime("%Y", $tr->getLargest(DAT)); $year++) {
	$pageMain->addOutput("<li><b>$year</b> : ");
	for ($month = 1; $month <= 12; $month++) {
        if ($tr->getSmallest(DAT) > mktime(0,0,0,$month, 1, $year) ||
            $tr->getLargest(DAT) < mktime(0,0,0,$month, 1, $year)) {
            $pageMain->addOutput(strftime("%B", mktime(0,0,0,$month,1,0))." ");
        } else {
            $pageMain->addOutput("<a href='rabograp_".$year."_".sprintf("%02d", $month).".html'>".strftime("%B", mktime(0,0,0,$month,1,0))."</a> ");
        }
	}
	$pageMain->addOutput("</li>\n");
}
$pageMain->addOutput("</ul>");
$pageMain->save("rabograp.html");

$pageAll = new RReport("Totalen voor alles");
$pageAll->addOutput("<a name='overview'><h1>Overzicht</h1></a>\n");
$pageAll->addOutput($pageAll->genOverviewNrOfTrans($tr));
$pageAll->addOutput($pageAll->genOverviewDebCred($tr));
$pageAll->addOutput($pageAll->genTransPerCounterParty($tr));
$pageAll->addOutput($pageAll->genTransAll($tr));
$pageAll->save("rabograp_all.html");

/* Year dumps */
for($year = (int)strftime("%Y", $tr->getSmallest(DAT)); $year <= (int)strftime("%Y", $tr->getLargest(DAT)); $year++) {
	$tYear = $tr->getFromTo(mktime(0,0,0,1,1,$year), mktime(0,0,0,1,1,$year+1));

	$pageYear = new RReport("Totalen voor ".$year);
	$pageYear->addOutput("<a name='overview'><h1>Overzicht</h1></a>\n");
	$pageYear->addOutput($pageYear->genOverviewNrOfTrans($tYear));
	$pageYear->addOutput($pageYear->genOverviewDebCred($tYear));
	$pageYear->addOutput($pageAll->genTransPerCounterParty($tYear));
	$pageYear->addOutput($pageYear->genGraphAllMonths($tYear));
	$pageYear->addOutput($pageYear->genTransAll($tYear));
	$pageYear->save("rabograp_$year.html");

	/* Per month */
	for ($month = 1; $month <= 12; $month++) {
        if ($tr->getSmallest(DAT) > mktime(0,0,0,$month, 1, $year) ||
            $tr->getLargest(DAT) < mktime(0,0,0,$month, 1, $year)) {
            ;
        } else {
            $tMonth = $tr->getFromTo(mktime(0,0,0,$month,1,$year), mktime(0,0,0,$month+1,1,$year));
            $pageMonth = new RReport("Totalen voor ".strftime("%B", mktime(0,0,0,$month,1,0))." ".$year);
            $pageMonth->addOutput("<a name='overview2'><h1>Overzicht</h1></a>\n");
            $pageMonth->addOutput($pageMonth->genOverviewNrOfTrans($tMonth));
            $pageMonth->addOutput($pageMonth->genOverviewDebCred($tMonth));
			$pageMonth->addOutput($pageAll->genTransPerCounterParty($tMonth));
            $pageMonth->addOutput($pageMonth->genTransAll($tMonth));
            $pageMonth->save("rabograp_".$year."_".sprintf("%02d",$month).".html");
        }
	}
}

?>
