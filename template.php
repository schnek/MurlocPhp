#MURLOC_PLUGIN_FORMAT 1.1
<?PHP
	/******************************************
	 * template plugin
	 * Version: 0.1
	 * Requirements: PHP >= 4
	 ******************************************/

	/* register the plugin
	 * action: command line switch to trigger this plugin
	 * usage: usage string, i.e. short (one-line) help text
	 * init: name of the function to initialise the plugin
	 * help: name of the function to display help
	 */
	registerPlugin("test",
		"test [arg1[, arg2[...]]] : a template plugin. Use any number or parameters you like.",
		"template_init",
		"template_help");
	//no other code should be executed in the body of this file, you only need to define some functions
	 

	/* function to initialise the plugin
	 * it should check the command line arguments ($args) and call
	 * registerParser at least once
	 * should return the unused part of $args or false on error */
	function template_init($args) {
		logMsg("template: initialising", DEBUG);
		unset($args[0]); //contains the action. don't pass this back!
		if(!registerParser("http://localhost/index.html?entry=%i", "template_out.sql", 1, 1,
			"template_parse", "template_preParse", "template_postParse")) {
			logMsg("template: Could not register parser.", CRITICAL);
			return false;
		}
		//let's eat up all the other parameters, they are yummy
		$args = array();
		return $args;
	} //template_init($args)

	/* called before parsing starts
	 * use it e.g. to write a comment the output file
	 * should return false on error, true otherwise
	 * this function is optional
	 * this function may take another parameter */
	function template_preParse($start, $end, $filename, $filehandle) {
		fwrite($filehandle, "-- this is the beginning\n");
		return true;
	} //template_preParse(...)

	/* called after parsing finished
	 * should return false on error, true otherwise
	 * this function is optional
	 * this function may take another parameter */
	function template_postParse($start, $end, $filename, $filehandle) {
		fwrite($filehandle, "-- this is the end\n");
		return true;
	} //template_postParse(...)

	/* function to do the actual parsing
	 * should return a query or false if an error occured
	 * this function may take another parameter */
	function template_parse($page, $entry) {
		logMsg("Parsing: $entry", DEBUG);
		return "";
	} //template_parse(...)

	/* function to display an extended help text
	 * no need to return anything */
	function template_help() {
		echo "This plugin has no actual use. It is only a template to demonstrate, how to write a plugin.\n";
		echo "Some parameters are handled on a global basis, like --help.\n";
		echo "But most parameters should be handled by the plugin. This one will accept any number of parameters and leave nothing to other plugins.\n";
	} //template_help()
?>
