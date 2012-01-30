Second Crack is a basic static-file blog engine using Markdown-formatted text files as input.

# Warning

Second Crack should be considered an early alpha. Even though I've run Marco.org with it for a long time, **it's still rough and unfriendly.**

There are also still **significant known bugs** that I haven't had time to fix yet. (Feel free!) Among them:

* The month archives sometimes only contain a few posts.
* If you have a day with multiple posts, and you delete one of them in the middle, sometimes the others don't reorder themselves properly.
* Editing slugs after publishing is unreliable.

These can usually be fixed temporarily by clearing the `cache/` directory's contents and running the updater for a full rebuild.

Other minor bugs:

* If you have multiple posts on the frontpage or an archive/tag page that use footnotes, the anchors will be repeated between posts and they won't work properly. Footnote anchors need some kind of unique per-post prefix.
* If a template is edited, the changes won't propagate to the entire site automatically -- only new posts and updated pages will show the changes. To propagate a template's changes across the whole site, do a full rebuild by clearing the `cache/` directory and running the updater.

# Installation

**Second Crack requires PHP 5.3+ and has only been tested so far on Mac OS X and CentOS 5.5, with Apache httpd.** It's already fast with Apache, but it can be even faster and capable of even more burst traffic if you put a caching proxy such as Varnish in front of it.

Check this directory out on the server (or locally on Mac or Linux for testing, if you'd like).

Add a line like this to your `crontab`, replacing `{SOURCE_PATH}` (your blog source directory, see **Basics** below) and `{SECONDCRACK_PATH}` (this checkout) accordingly:

    * * * * * /home/marco/secondcrack/engine/update.sh {SOURCE_PATH} {SECONDCRACK_PATH}

The updater script will append its output to `/tmp/secondcrack-update.log`.

I strongly suggest installing <a href="https://github.com/rvoicilas/inotify-tools/wiki">inotify-tools</a> to get `inotifywait`. With it, the Second Crack updater will respond to file changes immediately as they happen. Without it, it only updates as often as the `cron` task runs. The round-trip Dropbox updating is a **lot** faster and more awesome with `inotifywait`.

Your webserver should use `{SECONDCRACK_PATH}/www` as its document root. It must support `.htaccess` files and `mod_rewrite`. Second Crack will install its `engine/default.htaccess` file into the web document root as `.htaccess` if an `.htaccess` file doesn't already exist.

# Basics

Each `.txt` blog-post file is structured like this:

    This is the post title
    ======================
    Tags: tag1, tag2
    Published: 2012-01-03 10:22:31pm
    Type: link
    
    This is the post body, in **Markdown**.
    
    It will continue for the rest of the file. See, the top is a lot
    like an HTTP response with headers, but with a title and a row of 
    any number of equals signs ("===") as the first two headers.
    
    You can add arbitrary fields, formatted like HTTP headers. You
    can then customize the templates to respond to them.

The engine expects a folder structure like this: (this is your `{SOURCE_PATH}` mentioned above)

    (blog-source folder, name it whatever you want)/
        drafts/
            draft-post-slug.txt
            another-draft-post-slug.txt
            ...
        media/
            (images and other files however you want to arrange them. I do it like this:)
            2010/
            2011/
            2012/
                01/
                    post-slug.png
                    another-day-another-post1.jpg
                    another-day-another-post2.jpg
                    ...
        pages/
            about.txt
            contact-me.txt
            ...
        posts/
            2010/
            2011/
            2012/
                01/
                    20120102-01-post-slug.txt
                    20120103-01-another-day-another-post.txt
                    20120104-01-something-happened-today.txt
                    20120104-02-something-else-happened-and-its-still-today.txt
        templates/
            (this can technically be anywhere but it's easiest to keep it here)
            main.php
            rss.php
        hooks/
            (optional, code that executes on every new post, see example-hooks)
            post_twitter.php

If you're going to use Dropbox to publish, put that top-level blog-source folder somewhere in your Dropbox folder.

# Set up config.php

Copy `{SECONDCRACK_PATH}/config.php.example` to `{SECONDCRACK_PATH}/config.php` and edit it to reflect your paths and preferences.

The blog-source folder (`{SOURCE_PATH}`) should be set as `Updater::$source_path`. The path to `templates/` should be assigned to `Template::$template_dir`.

# Posting

Make a file in `drafts/` named as `post-slug-you-want-to-create.txt` like this:

    My draft title
    ==============
    
    Draft content.

(Don't set a `Published:` header. The engine will add that automatically later.)

When the updater runs next, the engine will create a sister file in a `_previews/` directory (and a `_publish-now/` directory, if it doesn't already exist), like this:

    drafts/
        post-slug-you-want-to-create.txt
        ...
        _previews/
            post-slug-you-want-to-create.html
            ...
        _publish-now/

Whenever you want to preview your post, just save the `.txt` file, run the updater (or wait for it to run itself), and open the corresponding `.html` file in your browser. When I'm writing, I just keep it open and hit Refresh to see changes.

To publish a post, either move it into the `_publish-now/` directory, or add a header that simply says "publish-now", like this:

    My finished title
    =================
    Tags: whatever, optional
    publish-now
    
    Here's my finished content.

Once that file is saved and the updater runs, it will publish the post immediately, removing it from `drafts/` (and removing its preview file) and moving its final copy into `posts/` in the appropriate year/month subdirectory.

To edit a post, simply edit its file in `posts/`. The updater will notice the change next time it runs and will update the published post.

# Posting with bookmarklets

Second Crack comes with a pair of convenience bookmarklets, "Draft Link" and "Draft Article", that create draft posts from the current page and any selected text.

To enable this, create a second web root on an alternate domain, e.g. `admin.myblog.com`, using the `api-www/` folder as its document root. Then you can navigate to, e.g.:

    http://admin.myblog.com/add-draft.php

...and install the bookmarklets from there.

Your username and password to use these are set in `config.php` under "Meta Weblog API params". (I wanted to make a Meta Weblog interface in here too, but haven't gotten to it.)

# Dropbox sync (strongly recommended)

Here's <a href="http://www.youtube.com/watch?v=cu5uXXulnNk">a video demo</a> of why you *really* want Dropbox sync and those bookmarklets.

Second Crack's updater examines the blog source directory for changes, then writes HTML files into the web directory as needed.

If you're writing on a computer, you probably have a server hosting the blog somewhere, and need some way to update the content on the server. The best way to do this, and the setup that Second Crack is designed to operate in, is to set up Dropbox in both places: the native client for your computer, and the <a href="https://www.dropbox.com/install?os=lnx">Linux CLI client</a> on the server.

**NOTE:** If you don't want your server to have access to your entire Dropbox folder, you can do a dual-account trick: create a second Dropbox account, set up _that_ one on the server, and "share" the blog-source folder from your primary account to the second account on Dropbox.

With Dropbox set up, the publishing and previewing workflow is streamlined, and you never need to run the Second Crack updater manually:

* Have the preview `.html` file open in your local browser.
* Make changes to the draft `.txt` file. Hit Save in your editor.
* Wait a few seconds. The changes will round-trip to the server and the `.html` file will update.
* Hit Refresh in the browser and see the changes you made, rendered right in your site's layout by the server-side engine.

Even when you publish, just write the "publish-now" header, hit Save, and close the file. Within seconds, it will disappear from the `drafts/` folder and the post will be published.

It's pretty awesome.

And if you want to edit your blog on the iPhone or iPad, you can do it with any Dropbox-capable text editor.

# FAQ

## Don't a lot of these static-file blogging engines already exist? 

Yes.

## Have you tried [existing solution]?

No.

## Isn't this reinventing the wheel?

Yes.

## Don't you have other things you could be working on?

Yes. (Don't we all?)

## This name isn't unique.

You're probably right. Neither the domain nor the Twitter username are available, and it's probably trademarked in an industry I've never heard of.

Really, it's a terrible idea to launch a major project with such an unavailable name. But this isn't a major project, and I don't intend for it to get widespread enough that those problems will ever matter.

I needed a name. This came to mind. It's a coffee-roasting term for the moment in the roast that the bean audibly pops for the second time, indicating development of the strongest flavors and the point that you should stop the roast because it's done.

## You're not entirely correct on that definition. And I prefer my roast to be [x] seconds (before|after) second crack.

I know. For the purposes of this FAQ, it's not really relevant.

## OK, back to the static-file blog engine. What have you done differently from [existing solution]?

A bunch of small things, probably. I don't know enough about the other solutions to really say.

## Why doesn't it have [feature]?

Because I didn't think [feature] needed to be there. Some anticipated frequent values for [feature]:

Comments: Use Disqus or Facebook comments. Or just go without comments. Do you really need them?  
Stats: Use Google Analytics or Mint. (Or both.)  
Widgets and dynamic page content: Use Javascript.  
Dynamic rendering for automatic mobile layouts, etc.: Use CSS.  

## Why should I use this instead of [existing solution]?

I don't know. You probably shouldn't.

## Will this make you, me, or anyone any money?

I doubt it.

## So why did you make this?

Because I'm a programmer, and this is what I do.

Some people jog away from their house every day, only to jog back. Others walk on a treadmill, expending energy to get nowhere. In both cases, it may appear to others that they've accomplished nothing, but they've chosen to do these seemingly redundant activities on a regular basis to incrementally improve themselves. And it works.

## That's not a perfect analogy. Programming another version of something with lots of existing solutions is nothing like daily cardiovascular exercise.

I know.
