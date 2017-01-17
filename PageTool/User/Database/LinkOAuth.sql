INSERT INTO User_OAuth (
  FK_User,
  oauth_uuid,
  oauth_name
) VALUES (
  :FK_User,
  :oauth_uuid,
  :oauth_name
);

UPDATE
  User
SET
  dateTimeIdentified = NOW(),
  FK_User_Type       = 2
WHERE
  ID = :FK_User;
