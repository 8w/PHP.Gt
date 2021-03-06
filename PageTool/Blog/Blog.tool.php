<?php class Blog_PageTool extends PageTool {
/**
 * The Blog PageTool is a generic web log tool, used to create one or more blogs
 * in your website that have titles, authors, content, tags and comments.
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
public $blogName = "Blog";
private $_dtFormat = "jS F Y";
private $_urlFormatArray = array(
	// An array of regular expressions used to check if the curent URL is
	// in the expected format. All URLs implicitly start with /Blog/ (or what
	// the blogName is set to), and these regular expressions check the 
	// remainding portion of the URL.

	// An article:
	"/^[0-9]{4}\/[A-Z][a-z]+\/[0-9]{0,2}\/[A-Za-z\-]+\.html$/",
	"/^Archive\.html$/",
	"/^Category\/[A-Z0-9][a-z0-9\-]*\.html$/",
	"/^About\/[a-z0-9\-_]+\.html$/",
);
/**
 * Called to output a single blog file, according to current URL.
 * URLs have to be generated by the getUrl function - in this style:
 * /Blog/2012/Feb/20/123-Blog+title.html (where 123 is the ID).
 */
public function go($api, $dom, $template, $tool) {
	// TODO: No need for numeric ID in URL when the date and title are present.
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
 * Checks the format of the current URL. If the format matches, return true, 
 * otherwise return false. It is the responsibility of the application to then
 * deal with the response and emit suitable HttpErrors.
 * @return Boolean true if the format matches what is expected, otherwise false.
 */
public function checkUrl() {
	$url = strtok($_SERVER["REQUEST_URI"], "?");
	$url = substr($url, strlen("/{$this->blogName}/"));
	foreach ($this->_urlFormatArray as $urlFormat) {
		if(preg_match($urlFormat, $url)) {
			return true;
		}
	}

	return false;
}

public function clientSide() {
	// Ensure that Font Awesome is loaded, for the iconset.
	$fontAwesomeExists = false;

	$linkList = $this->_dom["head link"];
	foreach ($linkList as $link) {
		$href = $link->getAttribute("href");
		if(preg_match("/\/Font\/FontAwesome\.css/i", $href)) {
			$fontAwesomeExists = true;
		}
	}

	if(!$fontAwesomeExists) {
		$publicDir = APPROOT . "/www/Font/";
		if(!is_dir($publicDir)) {
			mkdir($publicDir, 0775, true);
		}
		$dir = dir(GTROOT . "/Style/Font");
		while(false != ($file = $dir->read()) ) {
			if($file[0] == ".") {
				continue;
			}
			if(!strstr($file, "fontawesome")) {
				continue;
			}
			copy($dir->path . "/$file", $publicDir . $file);
		}
		$dir->close();
		// copy(GTROOT . "/Style/Font/FontAwesome.css", 
		// 	$publicDir . "FontAwesome.css");
		$link = $this->_dom->create(
			"link", [
			"href" => "/Font/FontAwesome.css",
			"rel" => "stylesheet"
		]);
		$this->_dom["head"]->append($link);
	}

	// TODO : Complete checking for font awesome.
	parent::clientSide();
}

/**
 * Gets Blog User details from the database linked to the supplied User account.
 * Creates a new Blog User if one doesn't exist.
 * @param  array 	$user 	The current user details
 * @return array 			An array of Blog_User details.
 */
public function getBlogUser($user) {
	do {
		$dbResult = $this->_api[$this]->getBlogUserByUserID($user);
		if(!$dbResult->hasResult) {
			$this->_api[$this]->createBlogUser($user);
		}
	} while(!$dbResult->hasResult);

	return $dbResult->result[0];
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
	$dbBlogList = $this->_api[$this]->getArticles([
		"name_Blog" => $this->blogName,
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
 * Sets the name of the blog, that is used in the generation of URLs. Ensures
 * there is a blog of that name in the database.
 * @param string $name The name of the blog.
 */
public function setName($name) {
	$this->blogName = $name;

	$dbResult = $this->_api[$this]->getBlogByName(["name" => $name]);
	if(!$dbResult->hasResult) {
		$this->_api[$this]->create(["name" => $name]);
	}
}

/**
 * Builds a string containing the absolute URL to a specified blog, according to
 * the blog's name, and the blog's attributes.
 * @param  array $blogObj Associative array of blog details.
 * @return string         Absolute URL to the blog.
 */
public function getUrl($blogObj) {
	$dtPublish = new DateTime(
		empty($blogObj["dateTimePublished"])
			? $blogObj["dateTimeCreated"]
			: $blogObj["dateTimePublished"]
	);

	$url = "/{$this->blogName}/";
	$url .= $dtPublish->format("Y/M/d/");

	// Transliterate characters not in ASCII, for example "café" => "cafe".
	$title = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $blogObj["title"]);
	$title = str_replace(" ", "_", $title);
	$title = preg_replace("/\W+/", "", $title);
	$title = preg_replace("/\s+/", "-", $title);
	$title = str_replace("_", "-", $title);
	$title = str_replace("--", "-", $title);
	$url .= urlencode($title);
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


	$url = "/{$this->blogName}/Tagged/";
	$url .= urlencode($name);
	$url .= ".html";
	return $url;
}

}#