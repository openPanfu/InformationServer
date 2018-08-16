-- This will add secret_key to your database, incase you're using the base.sql file.
alter table `gameservers` add `secret_key` varchar(64) null after `goldpanda`