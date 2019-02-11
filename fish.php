MURLOC_PLUGIN_FORMAT 1.0
<?PHP
	/******************************************
	 * fishing loot plugin
	 * Version: 0.1
	 * Requirements: PHP >= 4.3.0
	 ******************************************/

	/*register the plugin*/
	registerPlugin("loot_fishing",
		"loot_fishing <min> <max> : parse fishing data from <min> to <max> (area IDs)",
		"fish_init",
		"fish_help");

	/* function to initialise the plugin */
	function fish_init($args) {
		logMsg("fish: init with ".implode(" ", $args), DEBUG);
		$min = $args[1]; $max = $args[2];
		if(!isset($args[1])) {
			fish_help();
			return false;
		}
		if(!is_numeric($min) || !is_numeric($max) || $min <= 0 || $max < $min) {
			logMsg("fish: Incorrect parameters. Try --help for more information.", CRITICAL);
			return false;
		}
		if(!registerParser("http://thottbot.com/zf%i", "loot_fishing.sql", $min, $max,
			"fish_parse", "fish_preParse", "fish_postParse")) {
			logMsg("fish: Could not register parser.", CRITICAL);
			return false;
		}
		unset($args[0]); unset($args[1]); unset($args[2]);
		return $args;
	} //fish_init($args)

	/* called before parsing starts */
	function fish_preParse($start, $end, $filename, $filehandle) {
		fwrite($filehandle, "-- loot_fishing data for areas $start - $end\n");
		fwrite($filehandle, "-- source: http://thottbot.com/\n");
		fwrite($filehandle, "-- time: ".date("Y-m-d")."\n");
		return true;
	} //fish_preParse(...)

	/* called after parsing finished */
	function fish_postParse($start, $end, $filename, $filehandle) {
		fwrite($filehandle, "-- So long, and thanks for all the fish.\n");
		return true;
	} //fish_postParse(...)

	/* fish_getData($page, $entry, $lootTable, $heroic)
	 * function to parse one of (up to) two tables on the $page
	 * returns an array containing all relevant data from that table
	 * or false on error
	 */
	function fish_getData($page, $entry, $heroic = false) {
		$searchString = "Stocked with".($heroic ? " (Heroic)" : "")."</h4>";
		$tableStart = strpos($page, $searchString);
		if($tableStart === false)
			return false;
		$tableStart = strpos($page, "<tbody>", $tableStart);
		$tableEnd = strpos($page, "</tbody>", $tableStart);
		$table = substr($page, $tableStart, $tableEnd - $tableStart);
		//the table does not contain all data, there's (possibly)  more hidden behind it. *sigh*...
		$tableStart = strpos($page, "Table.add", $tableEnd);
		$tableEnd = strpos($page, "</script>", $tableStart);
		$hiddenTable = substr($page, $tableStart, $tableEnd - $tableStart);
		//loot count is below the actual (and the hidden) table, need to get that too
		$tableStart = strpos($page, "<table", $tableEnd);
		$tableEnd = strpos($page, "</table>", $tableStart);
		$tableLoot = substr($page, $tableStart, $tableStart - $tableEnd);

		//get the loot count for this area
		if(preg_match("!Looted ([0-9]+) times!i", $tableLoot, $matches) != 1)
			return false;
		$lootCount = $matches[1];

		//let's get the fish out of that table
		preg_match_all("!<a tt=.* href='i([0-9]+)'>.*</a>.*(?:<td align='center'>.*</td>){4}<td align='center'>([0-9]+)</td>!isU", $table, $matches);
		$id = $matches[1];
		$count = $matches[2];
		preg_match_all("!<a tt=.* href=\\\'i([0-9]+)\\\'>.*</table> '(?:,.*){4},'([0-9]+)',!isU", $hiddenTable, $matches);
		$id = array_merge($id, $matches[1]);
		$count = array_merge($count, $matches[2]);

		for($i = 0; $i < count($id); $i++) {
			$loot[] = array("id" => $id[$i], "percent" => 100 * $count[$i] / $lootCount);
		} //for $i

		return $loot;
	} //fish_getData(...)

	/* function to do the actual parsing
	 * should return a query or false if an error occured */
	function fish_parse($page, $entry) {
		
		logMsg("fish: parsing area $entry", DEBUG);

		$normalLoot = fish_getData($page, $entry);
		if($normalLoot === false) {
			logMsg("fish: error parsing page for area $entry. Skipping.", NOTICE); //something is fishy...
			return false;
		}
		//heroic table is not always present, so don't worry if the result is false
		$heroicLoot = fish_getData($page, $entry, true);

		//add the normal fish to the final table
		foreach($normalLoot as $line) {
			extract($line);
			$loot[$id] = array("entryid" => $entry, "itemid" => $id, "normal10percentchance" => $percent);
		} //foreach $line
		//merge heroic fish into it
		if($heroicLoot) {
			foreach($heroicLoot as $line) {
				extract($line);
				if(empty($loot[$id])) {
					$loot[$id] = array("entryid" => $entry, "itemid" => $id, "heroic10percentchance" => $percent);
				} else {
					$loot[$id]["heroic10percentchance"] = $percent;
				}
			} //foreach $line
		}

		//check for queer percentages
		//foreach($loot as &$line) { //this line can replace the next two for PHP5+
		foreach(array_keys($loot) as $i) { //this and the next are for PHP4-compability
			$line =& $loot[$i];
			$percent = max($line["normal10percentchance"], $line["heroic10percentchance"]);
			if($percent > 100) { //yes, this happens, let's spread this somehow
				$maxcount = ceil($percent / 100);
				$line["maxcount"] = $maxcount;
				if($line["normal10percentchance"] > 0) $line["normal10percentchance"] /= $maxcount;
				if($line["heroic10percentchance"] > 0) $line["heroic10percentchance"] /= $maxcount;
			}
		} //foreach $line
		unset($line);

		//put the queries togher
		$query = "DELETE FROM loot_fishing WHERE `entryid`='$entry';\n";
		foreach($loot as $line) {
			$keys = implode("`,`", array_keys($line));
			$values = implode("','", array_values($line));
			$query = $query . "INSERT INTO loot_fishing (`$keys`) VALUES ('$values');\n";
		} //foreach $line

		return $query;

	} //fish_parse(...)

	/* function to display an extended help text */
	function fish_help() {
		echo "loot_fishing parser. Usage:\n";
		echo "loot_fishing <min> <max>\n";
		echo "min : area id to start parsing at (positive integer)\n";
		echo "max : area id to stop parsing at (positive integer, larger than min)\n";
	} //fish_help()
?>
