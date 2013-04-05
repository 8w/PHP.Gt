update Blog_Article
set
	title = :title,
	content = :content,
	dateTimePublish = :dateTimePublish,
	isPrivate = :isPrivate,
	isFeatured = :isFeatured
where
	ID = :ID
limit 1;