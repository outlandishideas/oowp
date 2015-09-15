# Object-oriented WordPress (OOWP)

Contributors: harryrobbins, tamlyn, rasmuswinter, mattKendon, sdgluck, joaquimds
Tags: connections, custom post types, relationships, templating
Version: 0.9
Requires at least: 3.6
Tested up to: 4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Overview
OOWP is a tool for WordPress theme developers that makes templating in WordPress more sensible. It replaces [The Loop](https://codex.wordpress.org/The_Loop) and contextless functions such as `the_title()` with object-oriented methods such as `$event->title()`, `$event->parent()` and `$event->getConnected('people')`.

OOWP is designed to be used with themes with [custom post types](https://codex.wordpress.org/Post_Types) such as Events, People, Places, Articles, Recipes, etc. It doesn't currently work with the default post types (`Post`, `Page`) but this will be addressed in the forthcoming v1.0 release.

It is designed to work with the excellent [Posts 2 Posts](https://github.com/scribu/wp-posts-to-posts) plugin by Scribu and the just-as-excellent [Advanced Custom Fields](https://github.com/elliotcondon/acf) plugin by Elliot Condon and requires these plugins in order to take full advantage of its awesomeness. Some of the code samples below assume you have these plugins installed.

Instead of having to deal with The Loop and/all the weird WordPress magic of changing what the_post() refers to behind the scenes you can write nice object-oriented code like this:

```
foreach(ooPlace::fetchAll() as $place){
    print "<h2>Articles about $place->title<h2>";
    foreach($place->getConnected('article') as $article{
        print "<h3>" . $article->htmlLink(); . "</h3>";
        print $article->excerpt();
    }
}
```

Nice isn't it? If it's not nice and makes no sense whatsoever to you then you should either learn about object-oriented PHP (definitely a good idea) or look elsewhere (we won't be offended).


## Install
Whack it in WordPress's plugins directory and hack away at it until it works. Or wait until we create some better instructions.

## Basic usage
Once you have installed the plugin all `WP_Query` will return ooPost objects so that you can use the `$post->title()` syntax.

There are a range of simplified method names such as `$post->title()`, `$post->excerpt()`, and `$post->content()` as well as some useful helpers such as `$post->htmlLink()`. Don't worry if you've already swallowed the WordPress manual and learnt all the weird function names - if no specially defined OOWP method exists it will fallback to calling other declared user defined functions (including WordPress ones) after first calling `setup_postdata(ID)` where ID is the post_id of the post instance on which the method was called. This means that you can happily call `$post->get_title()` or `$post->the_title()` - though the latter will print the title rather than returning it which is undesirable in our opinion.

If no OOWP method exists, and no other user defined method exists then you will get the `Attempt to call non existenty method` error, which almost certainly means you're doing something wrong.

## Using custom post types
OOWP makes it easy to register custom post types...

Using OOWP custom post types makes it easier to structure your code by putting all your custom code - such as getting the time and place of an event - into the relevant custom post class.

## Connecting Custom post types
**This funtionality requires the [Posts 2 Posts](https://github.com/scribu/wp-posts-to-posts) plugin**
OOWP uses the Post2Post plugin to make it easy to connect and retrieve related posts. This functionality is designed to be used in place of the Tag and Category taxonomies that ship with WordPress. This has the advantage of reducing the number of different object types and methods that developers have to deal with, and allows the attaching of additional metadata such as author, geolocation, etc. to 'categories', which is not well supported by WordPress.

To register a connection between two post types declare a public static onRegistrationComplete() method which takes the same arguments as p2p_register_connection_type from Post2Post like so:

```
 public static function onRegistrationComplete() {

        parent::onRegistrationComplete();

        self::registerConnection("TARGET_POST_TYPE", array("cardinality"=>"many-to-many"));

    }
```

Where `TARGET_POST_TYPE` is the class name of the custom post type that you want to connect to, such as Person, Event, etc. If connecting to another custom post type it is good practice to replace `TARGET_POST_TYPE` with `TARGET_POST_TYPE::postType()` in case you decide to change the actual name of the post type later.

Once you have declared this function you can then easily fetch connected posts in your page templates using the `ooPost->getConnected()` method. For example:

```
foreach(ooPlace::fetchAll() as $place){
    print "<h2>Articles about $place->title<h2>";
    foreach($place->getConnected('article') as $article{
        print "<h3>" . $article->htmlLink(); . "</h3>";
        print $article->excerpt();
    }
}
```



