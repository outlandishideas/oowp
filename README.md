# Object-oriented WordPress (OOWP)

- Contributors: harryrobbins, tamlyn, rasmuswinter, mattKendon, sdgluck, joaquimds
- Tags: connections, custom post types, relationships, templating
- Version: 0.9
- Requires at least: 3.6
- Tested up to: 4.2
- License: GPLv2 or later
- License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Overview
OOWP is a tool for WordPress theme developers that makes templating in WordPress more sensible. It replaces [The Loop](https://codex.wordpress.org/The_Loop) and contextless functions such as `the_title()` with object-oriented methods such as `$event->title()`, `$event->parent()` and `$event->getConnected('people')`.

OOWP is designed to be used with themes with [custom post types](https://codex.wordpress.org/Post_Types) such as Events, People, Places, Articles, Recipes, etc. It doesn't currently work with the default post types (`Post`, `Page`) but this will be addressed in the forthcoming v1.0 release.

It is designed to work with the excellent [Posts 2 Posts](https://github.com/scribu/wp-posts-to-posts) plugin by Scribu and the just-as-excellent [Advanced Custom Fields](https://github.com/elliotcondon/acf) plugin by Elliot Condon and requires these plugins in order to take full advantage of its awesomeness. Some of the code samples below assume you have these plugins installed.

Instead of having to deal with The Loop and/all the weird WordPress magic of changing what the_post() refers to behind the scenes you can write nice object-oriented code like this:

```php
foreach ( ooPlace::fetchAll() as $place ) {
    print "<h2>Articles about {$place->title}<h2>";
    foreach ( $place->getConnected( 'article' ) as $article {
        print '<h3>' . $article->htmlLink() . '</h3>';
        print $article->excerpt();
    }
}
```

Nice isn't it? If it's not nice and makes no sense whatsoever to you then you should either learn about object-oriented PHP (definitely a good idea) or look elsewhere (we won't be offended).


## Theme structure

At Outlandish we use Bedrock to structure our projects and we recommend you do the same. It produces a folder structure like this:

    .
    ├── composer.json             # → Manage versions of WordPress, plugins & dependencies
    ├── config                    # → WordPress configuration files
    ├── vendor                    # → Composer packages (never edit)
    └── web                       # → Web root (vhost document root)
        ├── app                   # → wp-content equivalent
        │   ├── mu-plugins        # → Must use plugins
        │   ├── plugins           # → Plugins
        │   ├── themes            # → Themes - see below
        │   └── uploads           # → Uploads
        └── wp                    # → WordPress core (never edit)

> See the [Bedrock documentation](https://roots.io/bedrock/docs/folder-structure/) for more detail.

We structure our theme folders  like this:

    .
    └── web                         # → Web root (vhost document root)
        ├── app                     # → wp-content equivalent
        │   ├── themes              # → Themes 
        │   │   ├── sample-theme    # → An oowp-structured theme
        │   │   │   ├── assets      # → For static assets
        │   │   │   │   ├── fonts   # → webfont files
        │   │   │   │   ├── img     # → images used in the theme
        │   │   │   │   ├── js      # → the Javascript for the project which is minified and moved to ../public using a filewatcher
        │   │   │   │   ├── scss    # → SCSS which is transpiled into CSS and moved to the ../public folder using a filewatcher
        │   │   │   ├── public      # → Where assets from ../assets get built to by gulp scripts
        │   │   │   ├── src         # → The main theme files
        │   │   │   │   ├── PostTypes           # → For 'model' classes that represent custom post types 
        │   │   │   │   │   ├── BasePost.php    # → An abstract base post that contains functionality common to all the post types in this project (we often have more complex class hierarchies for, for example, hierarhical and non-hierarchical post-tyeps)
        │   │   │   │   │   ├── Blog.php        # → An outer 'layout' file that usually contains the header and footer and which is wrapped around the other views
        │   │   │   │   │   ├── Author.php      # → An outer 'layout' file that usually contains the header and footer and which is wrapped around the other views
        │   │   │   │   │   ├── ...             # → We often have five or more custom post types
        │   │   │   │   ├── Router              # → For 'controllers' that route URLs to responses via the relevant models and views
        │   │   │   │   │   ├── Router.php      # → The file which contains the mapping of routes (URL patterns) to controller functions that will generate and return a reponse
        │   │   │   │   ├── Views               # → For 'view' classes that subclass the OowpView or RoutemasterOowpView class
        │   │   │   │   │   ├── Components      # → For smaller templates that make up larger views
        │   │   │   │   │   ├── Layout.php      # → An outer 'layout' file that usually contains the header and footer and which is wrapped around the other views
        │   │   │   │   │   ├── DefaultPostView.php       # → A view that will be used to render single posts where no other template is defined
        │   │   │   │   │   ├── Layout.php      # → A view that will be used to render index/listing pages such as the homepage, blog index or category archive
        └────────────────────
        

        
        
## Install
Whack it in WordPress's plugins directory and hack away at it until it works. Or wait until we create some better instructions.

## Basic usage
Once you have installed the plugin all `WP_Query` will return WordpressPost objects so that you can use the `$post->title()` syntax.

There are a range of simplified method names such as `$post->title()`, `$post->excerpt()`, and `$post->content()` as well as some useful helpers such as `$post->htmlLink()`. Don't worry if you've already swallowed the WordPress manual and learnt all the weird function names - if no specially defined OOWP method exists it will fallback to calling other declared user defined functions (including WordPress ones) after first calling `setup_postdata(ID)` where ID is the post_id of the post instance on which the method was called. This means that you can happily call `$post->get_title()` or `$post->the_title()` - though the latter will print the title rather than returning it which is undesirable in our opinion.

If no OOWP method exists, and no other user defined method exists then you will get the `Attempt to call non existenty method` error, which almost certainly means you're doing something wrong.

## Using custom post types
OOWP makes it easy to register custom post types...

Using OOWP custom post types makes it easier to structure your code by putting all your custom code - such as getting the time and place of an event - into the relevant custom post class.

## Connecting Custom post types
**This funtionality requires the [Posts 2 Posts](https://github.com/scribu/wp-posts-to-posts) plugin**
OOWP uses the Post2Post plugin to make it easy to connect and retrieve related posts. This functionality is designed to be used in place of the Tag and Category taxonomies that ship with WordPress. This has the advantage of reducing the number of different object types and methods that developers have to deal with, and allows the attaching of additional metadata such as author, geolocation, etc. to 'categories', which is not well supported by WordPress.

To register a connection between two post types declare a public static onRegistrationComplete() method which takes the same arguments as p2p_register_connection_type from Post2Post like so:

```php
public static function onRegistrationComplete() {

    parent::onRegistrationComplete();

    self::registerConnection( 'TARGET_POST_TYPE', array( 'cardinality' => 'many-to-many' ) );

}
```

Where `TARGET_POST_TYPE` is the class name of the custom post type that you want to connect to, such as Person, Event, etc. If connecting to another custom post type it is good practice to replace `TARGET_POST_TYPE` with `TARGET_POST_TYPE::postType()` in case you decide to change the actual name of the post type later.

Once you have declared this function you can then easily fetch connected posts in your page templates using the `WordpressPost->getConnected()` method. For example:

```php
foreach ( ooPlace::fetchAll() as $place ) {
    print "<h2>Articles about {$place->title}<h2>";
    foreach ( $place->getConnected( 'article' ) as $article {
        print '<h3>' . $article->htmlLink() . '</h3>';
        print $article->excerpt();
    }
}
```

## WordPress.org

Unfortunately, for _new_ projects [WordPress is not accepting](https://make.wordpress.org/plugins/2016/03/01/please-do-not-submit-frameworks/)
libr1aries as plugins which they host. So direct usage with (non-[WPackagist](https://wpackagist.org/))
[Packagist](https://packagist.org/packages/outlandish/oowp) is the only sensible way to manage this dependency.
