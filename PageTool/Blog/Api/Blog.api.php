<?php class Blog_Api extends Api {

public $externalMethods = array("Save");

/**
 * This function is used to update an article from the client side. It executes
 * the UpdateArticle query, then removes all tags and re-assignes current tags.
 */
public function edit($params, $dal, $dalEl) {
	$ID = $_POST["ID"];
	$title = $_POST["title"];
	$content = $_POST["content"];

	$result = $dalEl->updateArticle([
		"ID" => $ID,
		"title" => $title,
		"content" => $content,
		"dateTimePublish" => date("Y-m-d H:i:s"),
		"isPrivate" => false,
		"isFeatured" => false,
	]);

	return $result;
}
}?>