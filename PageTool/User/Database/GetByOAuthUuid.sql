SELECT
  User.ID,
  User.uuid,
  User.username,
  User.dateTimeIdentified,
  User.dateTimeLastActive,
  User_Type.name        AS User_Type__name,
  User_OAuth.oauth_uuid AS oauthUuid,
  User_OAuth.oauth_name AS oauthProviderName

FROM User

  INNER JOIN User_Type
    ON (User_Type.ID = User.FK_User_Type)

  INNER JOIN User_OAuth
    ON (User_OAuth.FK_User = User.ID)

WHERE User_OAuth.oauth_uuid = :oauth_uuid
      AND User.dateTimeDeleted IS NULL
LIMIT 1;
