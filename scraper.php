<?php
/**
 * A custom PHP web scrapeer to find Happy Medium's blog posts living
 * in Concrete5 and convert them into Wordpress posts locally.
 *
 * This should probably be installed either as a Wordpress plugin or manually in the functions.php file.
 *
 * To run manually, do `php -S localhost:8888` and navigate to http://localhost:8888/scraper.php
 */

// Required, otherwise we get dumb errors (if not within Wordpress)
date_default_timezone_set('America/Chicago');

// Include our DOM parsing library
include('./vendor/ganon.php');

// Set some variables
$sitemap_url = 'http://www.itsahappymedium.com/sitemap.xml';
$sitemap = simplexml_load_file($sitemap_url);
$blogs = array();

// Loop through the sitemap and grab the url, category slug, and title slug of each blog post
foreach ($sitemap as $page) {
	if ( strstr($page->loc, '/blog/') ) {
		
		$blog['url'] = $page->loc . '';
		
		$url_chunks = explode('/', $blog['url']);
		
		if ( count($url_chunks) != 7 )
			continue;

		$blog['category_slug'] = $url_chunks[4];
		$blog['title_slug'] = $url_chunks[5];
		
		$blogs[] = $blog;
	}
}

// @TODO: Loop through each blog in the array
// 2. Grab the HTML through CURL
// 3. Get the title and the content (eliminate extra elements)
// 4. Use the Wordpress functions to create a new post. Insert the content, and set the page slug/category slug
// 5. Save the post.

$count = 0;

foreach ($blogs as $blog) {

	if ( $count == 10 )
		exit;

	$the_blog = $blog;
	$tags = array();

	echo '<hr><p>URL: ' . $blog['url'] . '</p>';

	// Load the page and process the HTML
	$the_blog_html = file_get_dom($the_blog['url']);

	// Remove the share widget
	$the_blog_html('div.share-widget', 0)->delete();

	// Grab the tags
	foreach ($the_blog_html('ul.tags li a') as $tag) {
		array_push($tags, $tag->getPlainText());
	}

	// Delete the tags
	$the_blog_html('div.blog-tags', 0)->delete();

	// Grab other important data
	$title = $the_blog_html('h1', 0)->getPlainText();
	// @TODO: Perhaps set authors beforehand and use an array to reference which ID they are by matching text
	$author = $the_blog_html('a[rel=author]', 0) ? $the_blog_html('a[rel=author]', 0)->getPlainText() : 'admin';
	$description = mb_convert_encoding( $the_blog_html('meta[name="description"]', 0)->content, "HTML-ENTITIES", "UTF-8" );
	$date_gmt = $the_blog_html('meta[property="article:published_time"]', 0)->content;
	$date = date("M j, Y", strtotime($date_gmt));

	// @TODO: Also lookup category by slug for insert_post

	// Convert the rest of the content to proper HTML and stuff.
	$content_html = htmlspecialchars(
		mb_convert_encoding(
			trim($the_blog_html('div.content', 0)->getInnerText()),
			"HTML-ENTITIES",
			"UTF-8"
		)
	);

	echo '<h2>' . $title . '</h2>';
	echo '<p>Author: ' . $author . '</p>';
	echo '<p>Date: ' . $date . '</p>';
	echo '<p>Title Slug: ' . $blog['title_slug'] . '</p>';
	echo '<p>Category Slug: ' . $blog['category_slug'] . '</p>';
	echo '<p>Tags: ' . implode(', ', $tags) . '</p>';
	echo '<p>Meta description: ' . $description . '</p>';
	echo '<pre>' . $content_html . '</pre>';

	// @TODO: Use wp_insert_post() to add this as a new post.
	// Grab the postID to use in future calls


	// @TODO: Loop through image tags
	// Determine if they're living on the Happy Medium server
	// Process them and add them as attachments

	$count++;
}
?>