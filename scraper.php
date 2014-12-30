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
include( '/www/sites/webscraper/vendor/ganon.php' );

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

	if ( $count == 1 )
		exit;

	$the_blog = $blog;
	$tags = array();

	// echo '<hr><p>URL: ' . $blog['url'] . '</p>';

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
	$date = date("Y-m-d H:i:s", strtotime($date_gmt));

	$category_slug = $blog['category_slug'];
	$category_id = '';
	if ( $category = get_category_by_slug( $category_slug ) ) {
		$category_id = $category->term_id;
	} else {
		$category_id = wp_insert_category( array(
			'category_name' => $category_slug,
			'category_nicename' => $category_slug
		));
	}

	$title_slug = $blog['title_slug'];

	// Lookup the author by name.
	// If they don't exist, create a new user.
	$user_id = '';
	$author_slug = trim( strtolower( str_replace(' ', '.', $author ) ) );
	$user = get_user_by('login', $author_slug);

	if ( $user ) {
		$user_id = $user->data->ID;
	} else {
		// Create new user
		$user_id = wp_insert_user( array(
			'user_login'	=> $author_slug,
			'user_pass'		=> NULL )
		);
	}

	// @TODO: Use wp_insert_post() to add this as a new post.
	$post_id = wp_insert_post( array(
		'post_content' => '',
		'post_name' => $title_slug,
		'post_title' => $title,
		'post_status' => 'publish',
		'post_type' => 'post',
		'post_author' => $user_id,
		'post_excerpt' => $description,
		'post_date' => $date,
		'post_category' => array($category_id),
		'tags_input' => $tags
	));

	// @TODO: Also get the meta thumbnail since that's important to have.

	// Store images in an array
	$images = array();
	$attachments = array();

	// Get all image tags
	$image_nodes = $the_blog_html('div.content img');

	// Loop through them
	foreach ($image_nodes as $image) {
		// Grab their original source
		$images[]['src'] = $image->src;
		$images[]['alt'] = $image->alt;
		$images[]['title'] = $image->title;

		$prefix = '';
		// Download each image and add it as an attachment to this page
		if ( substr($image->src, 0, 1) === '/' ) {
			// It's a local file
			$prefix = 'http://www.itsahappymedium.com';
		}

		// Upload image and attach it to the post
		$new_image_tag = media_sideload_image( $prefix . $image->src, $post_id, $image->alt );
		$image->setOuterText($new_image_tag);
	}

	// Loop back through each image node and replace the address of each image
	// with the updated wordpress one.
	foreach ($image_nodes as $idx => $image) {
		// $image_nodes[$idx]->src = $attachments[$idx]; // SET WP ATTACHMENT LINK HERE
		// Or just replace the entire inner text with the new image tag
		//
	}

	// Convert the rest of the content to proper HTML and stuff.
	// $content_html = htmlspecialchars(
	// 	mb_convert_encoding(
	// 		trim($the_blog_html('div.content', 0)->getInnerText()),
	// 		"HTML-ENTITIES",
	// 		"UTF-8"
	// 	)
	// );
	$content_html = trim($the_blog_html('div.content', 0)->getInnerText());

	// echo '<h2>' . $title . '</h2>';
	// echo '<p>Author: ' . $author . '</p>';
	// echo '<p>Date: ' . $date . '</p>';
	// echo '<p>Title Slug: ' . $blog['title_slug'] . '</p>';
	// echo '<p>Category Slug: ' . $blog['category_slug'] . '</p>';
	// echo '<p>Tags: ' . implode(', ', $tags) . '</p>';
	// echo '<p>Meta description: ' . $description . '</p>';
	// echo '<pre>' . $content_html . '</pre>';

	// @TODO: wp_update_post with the new, image-tag-filtered content
	var_dump($content_html);
	$response = wp_update_post( array(
		'ID' => $post_id,
		'post_content' => $content_html
	));

	var_dump($response);

	$count++;
}
?>