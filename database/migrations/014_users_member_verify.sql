ALTER TABLE `users`
    MODIFY COLUMN `role`
    ENUM('admin','editor','author','member') NOT NULL DEFAULT 'member';

ALTER TABLE `users`
    ADD COLUMN `email_verification_token` VARCHAR(64) NULL AFTER `email_verified_at`,
    ADD KEY `users_verify_token_idx` (`email_verification_token`);
