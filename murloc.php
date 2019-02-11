#!/usr/bin/php
<?PHP
/*******************************************************************************
 *
 *  Many Universally Reusable Lines Of Code (murloc)
 *	
 *  Version:       0.3.0 - 2010-02-16
 *  Plugin format: 1.1
 *  Requirements:  php-cli >= 4.3.0, allow_url_fopen = On or php-curl
 *
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 * 
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 *  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 *  ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 *  LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *  CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 *
 ******************************************************************************/

	set_time_limit(0);

	include_once("interface.php");

	//plugin format constants
	define(PLUGIN_FORMAT, "1.1"); //plugin format supported by this version
	define(PLUGIN_FORMAT_STRING, "MURLOC_PLUGIN_FORMAT"); //identifier that every plugin must include in it's first line

	//global variables
	$logLevel = NOTICE; //desired level of verbosity
	$plugins = array(); //list of available plugins
	$parsers = array(); //list of parsers to use
	$fetchTime = 0; //time spent fetching pages
	$parseTime = 0; //time spent by the plugins' parser functions
	$pageCounter = 0; //number of pages fetched totally

	/* formatTime($sec);
	 * returns a string containing the hours, minutes and seconds for $sec
	 */
	function formatTime($sec) {
		$hrs = floor($sec / 3600);
		$sec = $sec - $hrs * 3600; //simple % does not work due to floating point arithmetics
		$min = floor($sec / 60);
		$sec = round($sec - $min * 60, 2);
		return ($hrs > 0 ? "$hrs h, " : "").($min > 0 ? "$min m, " : "")."$sec s";
	} //formatTime(...)

	/* loadPlugins($dir)
	 * loads all valid plugins from $dir
	 * returns false on error, true otherwise
	 */
	function loadPlugins($dir = "") {
		if($dir == "")
			$dir = dirname(__FILE__); //search in this file's directory, unless otherwise specified

		$phpFiles = glob($dir."/*.php");
		if($phpFiles == false)
			return false;
		foreach($phpFiles as $filename) {
			//get the first line of the file
			$handle = fopen($filename, "r");
			$line = fgets($handle, strlen(PLUGIN_FORMAT_STRING) + 8);
			fclose($handle);

			//check if this a valid plugin
			if(strncmp($line, PLUGIN_FORMAT_STRING, strlen(PLUGIN_FORMAT_STRING)) != 0)
				continue; //this file is not a plugin, skip it
			$format = trim(strstr($line, " ")); //get the $format this plugin was written for
			if(floor($format) != floor(PLUGIN_FORMAT) || $format > PLUGIN_FORMAT) {
				logMsg("Plugin $filename has unsupported format $format. Skipping.", NOTICE);
				continue; //wrong format, skip it
			}

			//include it, but make PHP not echo the header line
			ob_start("stripHeader");
			include_once("$filename");
			ob_end_flush();
		} //foreach $filename

		return true;
	} //loadPlugins(...)

	/* stripHeader($buffer)
	 * if $buffer starts with the plugin header,
	 * this function removes the first line from it
	 * returns the resulting $buffer
	 */
	function stripHeader($buffer) {
		if(strncmp($buffer, PLUGIN_FORMAT_STRING, strlen(PLUGIN_FORMAT_STRING)) == 0) {
			$buffer = ltrim(strstr($buffer, "\n")); //should work on Windows. what about Mac?
		}
		return $buffer;
	} //stripHeaders(...)

	/* checkPlugin($plugin)
	 * checks if given $plugin has all necessary functions set
	 * returns false on error, true otherwise
	 */
	function checkPlugin($plugin) {
		return isset($plugin["action"]) &&
		       isset($plugin["usage"]) && 
		       function_exists($plugin["init"]) && 
		       function_exists($plugin["help"]);
	} //checkPlugin($plugin)

	/* checkParser($parser)
	 * checks if given $parser has all necessary functions set
	 * returns false on error, true otherwise
	 */
	function checkParser($parser) {
		return isset($parser["URL"]) &&
		       isset($parser["filename"]) &&
		       is_numeric($parser["start"]) &&
		       is_numeric($parser["end"]) &&
			   $parser["start"] <= $parser["end"] &&
			   $parser["start"] > 0 &&
			   function_exists($parser["parse"]) &&
			   (!isset($parser["preParse"]) || function_exists($parser["preParse"])) &&
			   (!isset($parser["postParse"]) || function_exists($parser["postParse"]));
	} //checkParser($parser)

	/* printHelp()
	 * print short usage information for every plugin
	 */
	function printHelp() {
		global $plugins;
		echo "I am Murloc\n";
		echo "Usage: ".$_SERVER["SCRIPT_FILENAME"]."[--loglevel <level>] <action> <parameters>\n";
		echo "You can set the loglevel to control the amount of messages that are printed to the console.\n";
		echo "  <level> can be one of debug, notice, warn or critical.\n";
		echo "Choices for <action> are listed below. <parameters> depends on <action>. Try <action> --help for more information.\n";
		echo "\n";
		foreach($plugins as $p) {
			echo $p["usage"]."\n";
		} //foreach $p
		echo "Rwl!\n";
	} //printHelp()

	/* parse($url_parsers, $min, $max)
	 * calls the parsers given in $url_parsers as well as their
	 * preParse and postParse functions
	 */
	function parse($url_parsers, $min, $max) {
		global $fetchTime, $parseTime, $pageCounter;

		logMsg("now parsing: ".$url_parsers[0]["URL"], DEBUG);
		
		//call the preParse functions
		//foreach($url_parsers as &$parser) { //this works on PHP5+ only
		foreach($url_parsers as $i => $parser) {
			extract($parser);
			
			if(file_exists($filename)) {
				rename($filename, $filename.".bak"); //make a backup of the output file
			}
			//$filehandle = $parser["filehandle"] = fopen($filename, "w"); //for PHP5+ only
			$filehandle = $url_parsers[$i]["filehandle"] = fopen($filename, "w");
			
			if(isset($preParse)) {
				if(isset($fctnArg))
					$preParse($start, $end, $filename, $filehandle, $fctnArg);
				else
					$preParse($start, $end, $filename, $filehandle);
			}
		} //foreach $parser
		unset($parser);

		//get the pages and pass them on to the parse functions
		for($entry = $min; $entry <= $max; $entry++) {
			$d = getTime();
			$page = getPage(str_replace("%i", $entry, $URL));
			$fetchTime += getTime() - $d;
			if($page == false)
				continue;
			$pageCounter++;
			foreach($url_parsers as $parser) {
				extract($parser);

				if($entry < $start || $entry > $end)
					continue;

				logMsg("running parser for $filename", DEBUG);
				$d = getTime();
				if(isset($fctnArg))
					$query = $parse($page, $entry, $fctnArg);
				else
					$query = $parse($page, $entry);
				$parseTime += getTime() - $d;
				if($query)
					fwrite($filehandle, $query);
			} //foreach $parser
		} //for $entry

		//call the postParse functions
		foreach($url_parsers as $parser) {
			extract($parser);
			if(isset($postParse)) {
				if(isset($fctnArg))
					$postParse($start, $end, $filename, $filehandle, $fctnArg);
				else
					$postParse($start, $end, $filename, $filehandle);
			}
			fclose($filehandle);
		} //foreach $parser
		
	} //parse(...)

	/* parserCompare($p1, $p2)
	 * helper function for sorting the array of parsers
	 * compares parsers by their start id
	 * returns -1 if $p1 < $p2, 1 if $p1 > $p2 and 0 if $p1 = $p2
	 */
	function parserCompare($p1, $p2) {
		if($p1["start"] == $p2["start"]) {
			if($p1["end"] == $p2["end"])
				return 0;
			return ($p1["end"] < $p2["end"] ? -1 : 1);
		}
		return ($p1["start"] < $p2["start"] ? -1 : 1);
	} //parserCompare(...)





	//load the plugins before parsing the command line
	if(!loadPlugins()) {
		logMsg("Error: Could not load plugins.", CRITICAL);
		exit(32);
	};
	//handle the command line arguments
	$args = $_SERVER["argv"];
	array_shift($args); //$args[0] is the name of this file, skip it
	if(empty($args)) {
		printHelp();
		exit;
	}
	while(!empty($args)) {
		$action = $args[0];
		if($action == "--help" || $action == "-h") {
			printHelp();
			exit;
		} elseif($action == "--loglevel") {
			switch($args[1]) {
				case "debug": $logLevel = DEBUG; break;
				case "notice" : $logLevel = NOTICE; break;
				case "warn" : $logLevel = WARN; break;
				case "critical" : $logLevel = CRITICAL; break;
				default: echo "Invalid loglevel '".$args[1]."'\n";
			}
			array_shift($args); array_shift($args); //remove both parameters from the list
			continue;
		} else {
			if(!array_key_exists($action, $plugins)) {
				logMsg("Error: Unknown action '$action'. Try --help for more information.", CRITICAL);
				exit(1);
			}
			if($args[1] == "--help" || $args[1] == "-h") {
				$plugins[$action]["help"](); //show the plugin's help
				exit;
			} else {
				$init = $plugins[$action]["init"];
				$result = $init($args); //initialise plugin
				if($result === false) {
					logMsg("Error: $init failed for $action.", CRITICAL);
					exit(2);
				}
				if($result == $args) {
					logMsg("Error in plugin $action.", CRITICAL); //stupid plugin didn't change $args
					exit(3);
				}
				$args = array_values($result); //recreate array keys, _must_ start at 0
			}
		}
	} //while(!empty($args))

	usort($parsers, "parserCompare"); //sort the array of parsers by start id

	//call all registered parsers
	while(!empty($parsers)) {
		$url_parsers = array(); //list of parsers with the same URL and overlapping range
		$p = array_shift($parsers);
		$URL = $p["URL"];
		$min = $p["start"];
		$max = $p["end"];
		$url_parsers[] = $p;
		foreach($parsers as $i => $p) {
			if($p["URL"] == $URL && $p["start"] <= $max + 1) { //this works because the array is sorted
				$url_parsers[] = $p;
				if($p["start"] < $min) $min = $p["start"];
				if($p["end"] > $max) $max = $p["end"];
				unset($parsers[$i]);
			}
		} //foreach $p
		logMsg("preparing to parse $URL ($min - $max)", DEBUG);
		parse($url_parsers, $min, $max);
	} //while(!empty($parsers))
	logMsg("total number of pages fetched: $pageCounter", NOTICE);
	logMsg("time spent fetching pages: ".formatTime($fetchTime), NOTICE);
	logMsg("time spent parsing pages: ".formatTime($parseTime), NOTICE);
?>
