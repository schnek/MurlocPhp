MURLOC_PLUGIN_FORMAT 1.0
<?PHP
	/******************************************
	 * quest reputation plugin
	 * Version: 0.1
	 * Requirements: PHP >= 4
	 ******************************************/

	/* register the plugin */
	registerPlugin("quest_rep",
		"quest_rep <min> <max> : parse quest reputation reward data from <min> to <max> (quest IDs)",
		"quest_rep_init",
		"quest_rep_help");
	 

	/* function to initialise the plugin
	 * it should check the command line arguments ($args) and call
	 * registerParser at least once
	 * should return the unused part of $args or false on error */
	function quest_rep_init($args) {
		logMsg("quest_rep: init with ".implode(" ", $args), DEBUG);
		array_shift($args); //remove the first element (action)
		if(!isset($args[0])) {
			quest_rep_help();
			return false;
		}
		$min = array_shift($args); $max = array_shift($args);
		if(!is_numeric($min) || !is_numeric($max) || $min <= 0 || $max < $min) {
			logMsg("quest_rep: Incorrect parameters. Try --help for more information.", CRITICAL);
			return false;
		}
		if(!registerParser("http://www.wowhead.com/?quest=%i", "quest_rep.sql", $min, $max,
			"quest_rep_parse", "quest_rep_preParse")) {
			logMsg("quest_rep: Could not register parser.", CRITICAL);
			return false;
		}
		return $args;
	} //quest_rep_init($args)

	/* called before parsing starts
	 * use it e.g. to write a comment the output file
	 * should return false on error, true otherwise
	 * this function is optional */
	function quest_rep_preParse($start, $end, $filename, $filehandle) {
		fwrite($filehandle, "-- reputation rewards for quests $start - $end\n");
		fwrite($filehandle, "-- source: http://www.wowhead.com/\n");
		fwrite($filehandle, "-- time: ".date("Y-m-d")."\n");

		define(ARCEMU_REP, 6); //number of reputation changes supported by the database structure (should be 9)
		$query = "UPDATE `quests` SET ";
		for($i = 1; $i <= ARCEMU_REP; $i++) {
			$query = $query . "`RewRepFaction$i`='0',`RewRepValue$i`='0',";
		}
		$query = rtrim($query, ","); //remove trailing ","
		if($start == $end)
			$query = $query . " WHERE `entry`=$start;";
		else
			$query = $query . " WHERE `entry` BETWEEN $start AND $end;";
		fwrite($filehandle, $query."\n");

		return true;
	} //quest_rep_preParse(...)

	/* function to do the actual parsing
	 * should return a query or false if an error occured */
	function quest_rep_parse($page, $entry) {
		logMsg("quest_rep: parsing quest $entry", DEBUG);
		define(ARCEMU_REP, 6); //number of reputation changes supported by the database structure (should be 9)

		$retVal = preg_match_all("!<li><div>(-?[0-9]+) reputation with <a href=\"/[?]faction=([0-9]+)\">.*</a></div></li>!isU", $page, $matches);
		if($retVal === false) //an error occured
			return false;
		if($retVal === 0) //nothing matched
			return "";

		$repValue = $matches[1];
		$repFaction = $matches[2];

		logMsg("quest_rep: ".implode(",", $repFaction)."->".implode(",", $repValue), DEBUG);

		//put the query together
		for($i = 1; $i <= count($repFaction); $i++) {
			$repUpdate[] = "`RewRepFaction$i`='".$repFaction[$i-1]."',`RewRepValue$i`='".$repValue[$i-1]."'";
		}
		$query = "UPDATE `quests` SET " . implode(",", array_slice($repUpdate, 0, ARCEMU_REP)) . " WHERE `entry`='$entry';";
		if(count($repUpdate) > ARCEMU_REP) //keep the additional values in a comment
			$query = $query . " -- " . implode(",", array_slice($repUpdate, ARCEMU_REP));

		return $query."\n";
	} //quest_rep_parse(...)

	/* function to display an extended help text
	 * no need to return anything */
	function quest_rep_help() {
		echo "quest reputation reward parser. Usage:\n";
		echo "quest_rep <min> <max>\n";
		echo "min : quest id to start parsing at (positive integer)\n";
		echo "max : quest id to stop parsing at (positive integer, larger than min)\n";
	} //quest_rep_help()
?>
