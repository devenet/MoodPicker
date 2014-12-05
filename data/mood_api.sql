CREATE TABLE `tokens` (
    `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    `token` TEXT NOT NULL,
    `expire` INTEGER NOT NULL,
    `api_key` TEXT NOT NULL
);

CREATE TABLE `credentials` (
	`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	`api_key` TEXT NOT NULL,
	`api_token` TEXT NOT NULL,
	`api_name` TEXT NOT NULL,
	`last_timestamp` INTEGER NOT NULL,
	`last_ip` TEXT  DEFAULT '0.0.0.0',
	`count` INTEGER DEFAULT 0
);