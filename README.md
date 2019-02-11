# Many Universally Reusable Lines Of Code (murloc)

## How to write a plugin for Murloc

Murloc provides a lot of functions to save you some of the dull work.

To write your own plugin, take a look at the provided template. This should
give you a quite good idea of what to do. If something is unclear, take a look
at this file. I hope to answer most questions that may arise here.

## I. Constraints

### 1. Filename

The plugin _must_ have the file extension 'php' for Murloc to detect and load it.
If you choose to include() other files in your plugin, you can name them any
way you like.

### 2. Header

The first line of any plugin identifies it as a Murloc plugin and states, what
format of the interface it was written for. It _must_ look exactly like the following
MURLOC_PLUGIN_FORMAT x.y
Don't put anything else on that line!
x.y stands for the format of the interface that your plugin is compatible
with. Simply use the number that your version of Murloc claims to support.
See below for details on the plugin format.
You'll notice there is a # at the beginning of the line in the template. I put
it there to disable the plugin (it's only a template, after all).

### 3. Things to call

There is only one call that should be necessary in the body of your PHP file.
You'll need to register your plugin with Murloc by calling

registerPlugin($action, $usage, $init, $help);
All four arguments are strings.
$action is the command line switch to trigger your plugin
$usage is a short text that describes how to use your plugin (one line)
$init is the name of the function to initialise the plugin. See below
$help another function, this one should display some helpful text
registerPlugin will return true on success or false if there was an error. You
don't have to check that, however.

Another function is usually called from the init function (see below):
registerParser($URL, $filename, $start, $end, $parse, $preParse, $postParse, $fctnArg);
$URL (string) is the URL of the pages your parser will need. %i will be
  replaced by the appropriate index. Example: "http://www.wowhead.com/?item=%i"
$filename (string) is the name of the output file (your SQL queries will be
  written to it). Example: "items_$start-$end.sql" (Don't pick the same as
  some other plugin!)
$start, $end (integer) are the range of pages you want to parse
$parse (string) name of the parser function (see below)
$preParse, $postParse (string) names of the functions that are called before
  or after parsing respectively. These are optional. Use null or omit them if
  you don't need them.
$fctnArg (any) is a custom parameter that will be passed to every invocation
  of $parse, $preParse and $postParse. Use this if you need additional custom
  parameters. This is optional.
  Tip: Use an array if you need multiple parameters.
registerParser will return true on success or false if there was an error.

### 4. Things to define

WARNING: When you write functions or define global variables, don't use the
same names like other plugins do! Make sure you pick unique names!
It is advisable to prefix everything you put in the global scope with some
string unique to your plugin (it's name, for example).

Obviously you will need to define the functions you named in the call to
registerPlugin(). But there are some others too.

init($args)
This function is called when switch for your plugin is found on the command
line.
$args is an array of all command line arguments starting with the string
to trigger your plugin ($action in the call to registerPlugin()). $args has
numerical keys that start from 0.
Your init function should return an array containing all command line
arguments that your plugin does not use. You can use unset() or array_shift()
to remove elements from $args and return what's left. You will always have to
remove the first element from $args.
If there was an error (e.g. invalid/too few aruments) this function should
return false.
The purpose of this function is to initialise your plugin. This means you
should check the command line arguments and make an appropriate call to
registerParser($URL, $filename, $start, $end, $parse, $preParse, $postParse);
See above for details on the parameters.

help()
This function is called when "--help" or "-h" is the first argument after your
plugins $action. So you don't even have to check for that in your init.
You may want to call it from init if there are problems with the command line
arguments.
The purpose of this function is to print a helpful message to the screen. It
should answer all the questions the user has about your plugin. And it should not
be too long.

parse($page, $entry)
parse($page, $entry, $fctnArg)
This function is called repeatedly during parsing (only if your plugin was requested
on the command line, of course).
$page is the full HTML-source of the page to parse, i.e. the one found at the
  URL you registered your parser with.
$entry is the index associated with the current page, i.e. the item id, quest
  id, NPC id, ... (depending on what you are parsing). It is an integer value
  between $start and $end (see registerParser above).
$fctnArg is an optional custom parameter. You need to pass it to
  registerParser to make use of this. If you don't pass an additional
  parameter to registerParser, you shouldn't expect one here.
Your parse function should return a string containing the query (or queries)
that results from the given page.
If an error occurred this function should return false.
The purpose of this function is to do the main work, i.e. get all the important
data from the given page and return it in form of a valid SQL-query.

preParse($start, $end, $filename, $filehandle)
preParse($start, $end, $filename, $filehandle, $fctnArg)
This function is called before parsing starts. Don't rely on it being called
/directly/ before your parse function is called first. Some time may pass
while other plugins process pages.
$start, $end, $filename are the values you passed to registerParser
$filehandle is an open (for write) filehandle to $filename
$fctnArg (see parse above)
The purpose of this function is up to you. You may want to write a comment to
the output file about what there is in it, where it comes from, when it was
parsed and so on. You may think of something else.
This function is optional and does not have to be defined.

postParse($start, $end, $filename, $filehandle)
postParse($start, $end, $filename, $filehandle, $fctnArg)
This function is called after parsing ended. As with preParse don't rely on it
being called /immediately/ after.
For notes on the parameters see preParse.
This function is rarely useful, but sometimes you may want to add some final
clean-up query. Or you may think of something else.
This function is optional and does not have to be defined.

### 5. Things to use

There are some functions you can use for your convenience. They are considered
part of the interface, i.e. the plugin format will be changed if these
functions are modified in a non-compatible way.
These 'public' functions are defined in 'interface.php'. All functions not
defined there are considered internal and may change without further notice!
The list here is not exhaustive, but covers only the most useful functions.
Look at 'interface.php' for a complete list.

logMsg($msg, $level = NOTICE)
prints $msg to stdout if $level is at least the loglevel desired by the user.
The message will be followed by a newline.
$level can be one of DEBUG, NOTICE, WARN and CRITICAL.
logMsg will return true or false, depending on weather the message was printed
or not.

registerPlugin($action, $usage, $init, $help)
see above

registerParser($URL, $filename, $start, $end, $parse, $preParser, $postParse, $fctnArg)
see above

### 6. Things not to do

Murloc will do a couple of things, so you don't have to worry about them.
These include (but are not limited to):

Make a backup of the output file
If the output file you specified exists, Murloc will, prior to writing to
it, move it to $filename.bak (overwriting any previous backup).

Open your output file
Murloc will open the file for writing and pass a handle to it to your preParse
and postParse functions, in case you want to write something to it.

## II. Additional Information

### 1. The plugin format

Whenever something in Murloc's interface for plugins (see above) is changed,
the plugin format is changed accordingly.
On a backwards compatible change (e.g. a new feature) the minor version of the
format is increased.
If a change is not compatible with plugins written before it, the major
version is increased (and the minor version reset).
For every plugin found, Murloc will automatically determine if it is
compatible with the current interface. If it is, Murloc will load it,
otherwise it will print a notice.
If, for example, the currently supported format is 3.14 a plugin that was
written for 3.8 will work, whereas one written for 2.7 will not (all numbers
are made up).

### 2. The order of calls

When Murloc is called, firstly it will look for plugins and load them (if the
format matches). That means, the body of your file is executed.
All the rest depends on the command line arguments.
Let's make an example. Murloc is called as
murloc.php items 3 14
(assuming items will trigger your plugin and 3 14 is a valid set of parameters
for it, specifying a start and end value for parsing).
Your plugin's init function is called with parameters ('items', '3, '14').
Let's further assume your init function called
registerParser('http://somesite.com/item=%i', 'items-3-14.sql', 3, 14,
  'my_parse', 'my_preParse', 'my_postParse');
Then he next thing that's called is my_preParse with parameters 
  (3, 14, 'items-3-14.sql', <some handle>)
Then my_parse is called with parameters 
  (<source of http://somesite.com/item=3>, 3)
[and so on in a loop ending at 14]
Lastly my_postParse is called with parameters
  (3, 14, 'items-3-14.sql', <some handle>)
