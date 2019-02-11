MURLOC_PLUGIN_FORMAT 1.1
<?PHP
	/******************************************
	 * item names plugin
	 * Version: 0.1
	 * Requirements: PHP >= 4.3.0
	 ******************************************/

	/* register the plugin */
	registerPlugin("item_names",
		"item_names <language>[ <language>[...]] <min> <max> : parse item names for <language> from <min> to <max> (item IDs)",
		"item_names_init",
		"item_names_help");

	global $item_names_supportedLangs; //needed because Murloc includes this file in a function
	$item_names_supportedLangs = array('de', 'en', 'es', 'fr', 'ru'); //keep this in lower case!
	 

	/* function to initialise the plugin
	 * it should check the command line arguments ($args) and call
	 * registerParser at least once
	 * should return the unused part of $args or false on error */
	function item_names_init($args) {
		global $item_names_supportedLangs;
		logMsg("item_names: init with ".implode(" ", $args), DEBUG);
		array_shift($args); //remove the first element (action)

		if(!isset($args[0])) {
			item_names_help();
			return false;
		}

		//get requested languages
		while(!is_numeric($args[0])) {
			$l = array_shift($args);
			if(!in_array(strtolower($l), $item_names_supportedLangs)) {
				logMsg("item_names: unsupported language '$l' Try --help for more information.", CRITICAL);
				return false;
			}
			$langs[] = strtolower($l);
		}

		$min = array_shift($args); $max = array_shift($args);
		if(empty($langs) || !is_numeric($min) || !is_numeric($max) || $min <= 0 || $max < $min) {
			logMsg("item_names: Incorrect parameters. Try --help for more information.", CRITICAL);
			return false;
		}

		foreach($langs as $l) {
			if(!registerParser("http://".($l == "en" ? "www" : $l).".wowhead.com/?item=%i",
				"item_names_$l.sql", $min, $max, "item_names_parse",
				"item_names_preParse", "item_names_postParse", $l)) {
				logMsg("item_names: Could not register parser for language $l.", CRITICAL);
				return false;
			}
		} //foreach $l

		return $args;
	} //item_names_init($args)

	/* called before parsing starts
	 * should return false on error, true otherwise */
	function item_names_preParse($start, $end, $filename, $filehandle, $lang) {
		fwrite($filehandle, "-- item names $start - $end\n");
		fwrite($filehandle, "-- language: $lang\n");
		fwrite($filehandle, "-- source: http://www.wowhead.com/\n");
		fwrite($filehandle, "-- time: ".date("Y-m-d")."\n");
		return true;
	} //item_names_preParse(...)

	/* called after parsing finished
	 * should return false on error, true otherwise */
	function item_names_postParse($start, $end, $filename, $filehandle, $lang) {
		if($lang != "en") {
			$lang = strtolower($lang).strtoupper($lang);
			fwrite($filehandle, "DELETE FROM `items_localized` WHERE `language_code`='$lang' AND `entry` NOT IN (SELECT `entry` FROM `items`);\n");
		}
		return true;
	} //item_names_postParse(...)

	/* function to do the actual parsing
	 * should return a query or false if an error occured */
	function item_names_parse($page, $entry, $lang) {
		if($lang == "en") 
			$lang = "enUS";
		else
			$lang = strtolower($lang).strtoupper($lang);

		logMsg("item_names: parsing item $entry ($lang)", DEBUG);

		//get the name
		$titleStart = stripos($page, "<title>");
		$titleEnd = stripos($page, "</title>", $titleStart);
		$title = substr($page, $titleStart, $titleEnd - $titleStart);
		if(strpos($title, "Error") || strpos($title, "Fehler") || strpos($title, "Erreur") || strpos($title, "Ошибка")) {
			logMsg("item_names: item $entry does not exists.", DEBUG);
			return false;
		}
		$name = substr($title, 7, strrpos($title, "-") - 7); //remove "<title>" and " - World of Warcraft"
		$name = trim(substr($name, 0, strrpos($name, "-"))); //remove "- Item" (in any language)
		if($name[0] == '[' && $name[strlen($name)-1] == ']') {
			logMsg("item_names: item not localised.", DEBUG);
			return false;
		}
		$name = str_replace("&nbsp;", " ", $name);
		$name = str_replace("  ", " ", $name);
		$name = html_entity_decode($name, ENT_QUOTES);

		//get the description
		//look for an explicit description first
		//alternatively look for "use:"
		if(preg_match("!<span class=\"q\">&quot;(.*?)&quot;</span>!i", $page, $matches) == 0) {
			preg_match("!<span class=\"q2\">(?:Use|Benutzen|Uso|Utiliser|Использование).{0,2}: (.*?)</span>!i", $page, $matches);
		}
		$description = $matches[1];
		if(strpos($description, "href=\"/?spell=")) //if "use:" links to a spell, there's no need for a description
			$description = "";
		$description = str_replace("&nbsp;", " ", $description);
		$description = str_replace("  ", " ", $description);
		$description = html_entity_decode($description, ENT_QUOTES);

		/*
		//get the language
		preg_match("!<link rel=\"search\".*title=\"Wowhead( \(..\))?\" />!", $page, $matches);
		if(isset($matches[1])) {
			$lang = trim($matches[1], " ()");
			$lang = strtolower($lang).$lang;
		} else {
			$lang = "enUS";
		}
		*/

		logMsg("item_names: Found item '$name' - '$description'", DEBUG);

		//escape ' and "
		$name = str_replace("\\\\", "\\", str_replace(array("'", '"'), array("\'", '\"'), $name));
		$description = str_replace("\\\\", "\\", str_replace(array("'", '"'), array("\'", '\"'), $description));

		//create the appropriate query
		if($lang == "enUS")
			$query = "UPDATE `items` SET `name1`='$name',`description`='$description' WHERE `entry`='$entry';\n";
		else
			$query = "REPLACE INTO `items_localized` (`entry`, `language_code`, `name`, `description`) VALUES ($entry,'$lang','$name','$description');\n";

		return $query;
	} //item_names_parse(...)

	/* function to display an extended help text
	 * no need to return anything */
	function item_names_help() {
		global $item_names_supportedLangs;
		echo "item names parser. Usage:\n";
		echo "item_names <language>[ <language>[...]] <min> <max>\n";
		echo "language : possible values are: '".implode("', '", $item_names_supportedLangs)."'\n";
		echo "  you can specify multiple languages to parse all of them\n";
		echo "min : item id to start parsing at (positive integer)\n";
		echo "max : item id to stop parsing at (positive integer, larger than min)\n";
	} //item_names_help()
?>
