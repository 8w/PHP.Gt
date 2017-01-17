UPDATE User
SET
  dateTimeDeleted     = NOW(),
  FK_User__orphanedBy = :ID
WHERE
  ID = :orphanedID
LIMIT 1;
