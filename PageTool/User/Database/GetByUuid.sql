SELECT
  User.ID,
  User.uuid,
  User.username,
  User.dateTimeIdentified,
	User.dateTimeIdentified IS NOT NULL AS isIdentified,
  User.dateTimeLastActive,
  User_Type.name AS User_Type__name
FROM User
  INNER JOIN User_Type
    ON (User_Type.ID = User.FK_User_Type)
WHERE uuid = :uuid
      AND User.dateTimeDeleted IS NULL
LIMIT 1;
