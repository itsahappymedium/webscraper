// This is a nodejs version of a scraper.
// 
// Run `node scrape.js` from the command line.

var request = require('request');
var cheerio = require('cheerio');

var url = 'http://www.itsahappymedium.com/blog/latest-news/integrus-credit-union-new-year-new-website/';
request(url, function(err, resp, body) {
	if ( err )
		throw err;
	$ = cheerio.load(body);
	var blogContent = $('.content');
	blogContent.find('.blog-tags').remove()
	blogContent.find('.share-widget').remove();
	var blogCopy = blogContent.text();
	console.log(blogCopy);
})