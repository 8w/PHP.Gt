select 
	`User_Type`.`ID` as `ID`,
	`User_Type`.`name` as `name`
from `User`
inner join `User_Type`
	on (`User_Type`.`ID` = `User`.`FK_User_Type`)
where `User`.`ID` = :ID
limit 1;