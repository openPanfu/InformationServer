-- Turns ticket_id into a varchar.
ALTER TABLE `users` CHANGE `ticket_id` `ticket_id` VARCHAR(11) NULL DEFAULT NULL; 