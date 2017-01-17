# Creates a new user identified by the Uuid.
INSERT INTO User (
  uuid,
  dateTimeCreated
)
VALUES (
  :uuid,
  NOW()
);
