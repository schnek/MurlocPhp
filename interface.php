<?PHP
/*************************************************************************
 * This file is part of Murloc.
 * See murloc.php for version and licencing details.
 *
 * All functions/constants in this file are considered part of Murloc's
 * interface to plugins. Plugins are encouraged to make use of them
 * and can rely on their interface to be stable for a given format.
 * If these functions change in a non-compatible way, the plugin format
 * will be increased accordingly.
 * Usage of functions defined in murloc.php is highly discouraged as they
 * may change without further notice!
 *
 *************************************************************************/

	//log level constants
	define(DEBUG, 0);
	define(NOTICE, 1);
	define(WARN, 2);
	define(CRITICAL, 3);

	/* registerPlugin($action, $usage, $checkargs, $help)
	 * adds a plugin with the given information to the list
	 * returns false on error, true otherwise
	 * parameters:
	 * $action (string) : command line switch to trigger this plugin
	 * $usage (string) : short (one-line) help text
	 * $init (string) : name of the function to initialise the plugin
	 * $help (string) : name of the function to display help
	 */
	function registerPlugin($action, $usage, $init, $help) {
		global $plugins;
		$plugin = compact("action", "usage", "init", "help");
		$result = checkPlugin($plugin);
		if($result) {
			$plugins[$action] = $plugin; //all good, add it
		} else {
			logMsg("Warning: Plugin $action disabled.", WARN);
		}
		return $result;
	} //registerPlugin(...)

	/* registerParser($URL, $filename, $start, $end, $parse, $preParse, $postParse, $fctnArg)
	 * adds a parser with given information to the list
	 * given functions will be called at the appropriate time
	 * returns false on error, true otherwise
	 * parameters:
	 * $URL (string) : base URL for this parser
	 * $filename (string) : name of the output file
	 * $start (integer) : id to start parsing at
	 * $end (integer) : id to stop parsing at
	 * $parse (string) : name of the parser function
	 * $preParse (string) : name of the preParse function (optional)
	 * $postParser (string) : name of the postParse function (optional)
	 * $fctnArg (any) : parameter to be passed to each of the above functions (optional)
	 */
	function registerParser($URL, $filename, $start, $end, $parse, $preParse = null, $postParse = null, $fctnArg = null) {
		global $parsers;
		$parser = compact("URL", "filename", "start", "end", "parse", "preParse", "postParse", "fctnArg");
		$result = checkParser($parser);
		if($result) {
			$parsers[] = $parser;
		} else {
			logMsg("Error: invalid parser for $URL", CRITICAL);
		}
		logMsg("Registerd parser for $filename.", DEBUG);
		return $result;
	} //registerParser(...)

	/* logMsg($msg, $level)
	 * prints $msg to stdout if it's $level is high enough
	 * return true if it was printed, false otherwise
	 */
	function logMsg($msg, $level = NOTICE) {
		global $logLevel;
		if($level < $logLevel)
			return false;
		echo $msg."\n";
		return true;
	} //logMsg(...)

	/* getTime()
	 * returns the current UNIX timestamp with microsecond percision
	 */
	function getTime() {
		if(PHP_VERSION >= "5.0.0") {
			return microtime(true);
		} else {
			list($usec, $sec) = explode(" ", microtime());
			return ((float)$usec + (float)$sec);
		}
	} //getTime()

	/* getPage($URL)
	 * fetches $URL and returns the page's source
	 * or false on error
	 */
	function getPage($URL){
		if(ini_get("allow_url_fopen")) {
			return @file_get_contents($URL); //don't echo errors
		} else {
			$ch = curl_init($URL);
			curl_setopt($ch, CURLOPT_FAILONERROR, true); //fail on error code >= 400
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //make curl_exec return the page instead of 'true'
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			$page = curl_exec($ch);
			curl_close($ch);
			return $page;
		}
	} //getPage($URL)
?>
