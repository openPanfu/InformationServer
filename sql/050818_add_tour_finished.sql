-- This will add tour_finished to your database, incase you're using the base.sql file.
alter table `users` add `tour_finished` tinyint not null default '0' after `current_gameserver`;