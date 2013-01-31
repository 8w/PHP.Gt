<?php class Blog_PageTool extends PageTool {
/**
 * The Blog PageTool is a generic web log tool, used to create one or more blogs
 * in your website that have titles, content, tags and comments.
 *
 * To use the PageTool, it is required that your HTML contains certain elements
 * for where to output the content to. This is explained in the ReadMe and in 
 * the official documentation on http://php.gt
 *
 * To get a blog article by ID, use the getArticle method.
 * To get a list of blogs in date order, use the getArticleList method.
 * To automatically output the blog's article according the the current URL,
 * simply use the go method.
 */
private $_blogName = "Blog";
private $_dtFormat = "jS F Y";
/**
 * Called to output a single blog file, according to current URL.
 * URLs have to be generated by the getUrl function - in this style:
 * /Blog/2012/Feb/20/123-Blog+title.html (where 123 is the ID).
 */
public function go($api, $dom, $template, $tool) {
	$blogID = substr(FILE, 0, strpos(FILE, "-"));
	if(!is_numeric($blogID)) {
		throw new HttpError(400);
	}

	$blog = $this->getArticle($blogID)[0];
	if(is_null($blog)) {
		throw new HttpError(404);
	}

	$blogUrl = $this->getUrl($blog);
	if($blogUrl !== $_SERVER["REQUEST_URI"]) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: $blogUrl");
		exit;
	}

	// Attempt to find the container for the blog.
	$container = $dom["body > section#st_article"];
	if($container->length == 0) {
		$container = $dom["body > section"];
	}
	if($container->length == 0) {
		$container = $dom["body"];
	}
	$this->output($blog, $container);
}

/**
 * Gets an associative array containing all article's details.
 * @param  int $ID ID of the article to select.
 * @return array     Associative array containing all article's details, or null
 * if no article is found.
 */
public function getArticle($ID) {
	$dbBlog = $this->_api[$this]->getArticleByID(array("ID" => $ID));
	if($dbBlog->hasResult) {
		return $dbBlog->result;
	}

	return null;
}

/**
 * Gets an array of blog details in chronological order, newest first.
 * @param  integer $limit How many blogs to retrieve (max).
 * @return array          Array of associative arrays containing blog details.
 */
public function getArticleList($limit = 10) {
	$dbBlogList = $this->_api[$this]->getLatestArticles([
		"name_Blog" => $this->_blogName,
		"Limit" => $limit
	]);

	return $dbBlogList->result;
}

/**
 * Outputs a given blog article to a particular DomEl container element.
 * @param  array $article  Associative array of article details
 * @param  DomEl $domEl The container for where to place the article.
 * @return DomEl        The container where the article has been placed.
 */
public function output($article, $domEl) {
	$template = $this->_template;

	$url = $this->getUrl($article);

	$domArticle = $template["Article"];
	
	$domArticle["header > h1 a"]->text = $article["title"];
	$domArticle["header > h1 a"]->href = $url;
	$dtPublish = new DateTime($article["dateTimePublish"]);
	$domArticle["header > p.date time"]->text = 
		$dtPublish->format($this->_dtFormat);
	$domArticle["header > p.date time"]->datetime = $article["dateTimePublish"];
	$domArticle["header > p.comments a"]->href = $url . "#Comments";
	$domArticle["header > p.comments span"]->text = 
		$article["num_Blog_Article_Comment"];

	$tagArray = explode(",", $article["list_Blog_Article_Tag"]);
	$domTagList = $domArticle["header > ul.tags"];
	foreach ($tagArray as $tag) {
		$domTag = $template["ArticleTagLink"];
		$domTag["a"]->href = $this->getTagUrl($tag);
		$domTag["a"]->text = $tag;

		$domTagList->append($domTag);
	}

	if($domEl->hasClass("preview")) {
		$domArticle["div.content"]->html = $article["preview"];
		$domArticle["footer p a"]->href = $url;
	}
	else {
		$domArticle["div.content"]->html = $article["content"];
		$domArticle["footer"]->remove();
	}

	$domEl->append($domArticle);

	// TODO: For non-preview articles, emit a new section (templated) for the 
	// comments.

	return $domEl;
}

/**
 * Sets the name of the blog, that is used in the generation of URLs.
 * @param string $name The name of the blog.
 */
public function setName($name) {
	$this->_blogName = $name;
}

/**
 * Builds a string containing the absolute URL to a specified blog, according to
 * the blog's name, and the blog's attributes.
 * @param  array $blogObj Associative array of blog details.
 * @return string         Absolute URL to the blog.
 */
public function getUrl($blogObj) {
	$dtPublish = new DateTime($blogObj["dateTimePublish"]);
	$url = "/{$this->_blogName}/";
	$url .= $dtPublish->format("Y/M/d");
	$url .= "/" . $blogObj["ID"] . "-";
	$url .= urlencode($blogObj["title"]);
	// TODO: Temp. remove periods as to not break URL regex.
	$url = str_replace(".", "", $url);
	$url .= ".html";
	return $url;
}

/**
 * Builds a string containing the absolute URL to a specified tag.
 * @param  array $tagObj Associative array of tag details.
 * @return string        Absolute URL to the tag page.
 */
public function getTagUrl($tagObj) {
	$name = is_string($tagObj)
		? $tagObj
		: $tagObj["name"];


	$url = "/{$this->_blogName}/Tagged/";
	$url .= urlencode($name);
	$url .= ".html";
	return $url;
}

}?>